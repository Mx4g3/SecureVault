"""Local integration tests. Uses only Python's standard library and disposable QA accounts."""
from otp_fixture import prepare_code
import html
import http.cookiejar
import json
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8085'
if not BASE.startswith(('http://127.0.0.1:', 'http://localhost:')):
    raise SystemExit('Tests are restricted to your local development app.')
results = []

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
    def request(self, path, data=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.opener.open(BASE + '/' + path, body, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode(), response.headers, response.url
    def token(self, path):
        status, text, _, _ = self.request(path)
        assert status == 200, (path, status)
        return re.search(r'name="csrf" value="([a-f0-9]+)"', text).group(1)
    def post(self, path, data, token_page=None):
        data = dict(data)
        data['csrf'] = self.token(token_page or path)
        result = self.request(path, data)
        if path == "login.php" and result[3].endswith("/verify_email.php"):
            prepare_code(data["email"])
            return self.post("verify_email.php", {"action":"verify","code":"654321"})
        return result

def check(name, condition):
    results.append({'test': name, 'passed': bool(condition)})
    print(('PASS ' if condition else 'FAIL ') + name)
    if not condition: raise AssertionError(name)

stamp = str(time.time_ns())
email_a = 'qa_a_' + stamp + '@example.test'
email_b = 'qa_b_' + stamp + '@example.test'
password = 'TestVault!98765'
a, b, anon = Client(), Client(), Client()
_, _, _, url = anon.request('dashboard.php')
check('Anonymous dashboard redirects to login', url.endswith('/login.php'))
status, _, headers, _ = anon.request('login.php')
check('Security headers and no-store cache policy', "frame-ancestors 'none'" in headers.get('Content-Security-Policy','') and headers.get('X-Content-Type-Options') == 'nosniff' and 'no-store' in headers.get('Cache-Control',''))
cookie = next(iter(anon.jar))
check('Session cookie is HttpOnly and SameSite=Lax', cookie.has_nonstandard_attr('HttpOnly') and cookie.get_nonstandard_attr('SameSite') == 'Lax')
check('Registration rejects missing CSRF', anon.request('register.php', {'username':'Test','email':email_a,'password':password})[0] == 403)
check('Registration validates username, email, password and confirmation', anon.post('register.php', {'username':'!','email':'invalid','password':'x','confirm_password':'y'})[0] == 422)
for client, email, name in [(a,email_a,'QA_A'),(b,email_b,'QA_B')]:
    status, body, _, url = client.post('register.php', {'username':name,'email':email,'password':password,'confirm_password':password})
    check('Register ' + name, status == 200 and url.endswith('/verify_email.php'))
    prepare_code(email)
    assert client.post('verify_email.php', {'action':'verify','code':'654321'})[3].endswith('/dashboard.php')
    client.post('logout.php', {}, 'dashboard.php')
check('Duplicate email fails safely', anon.post('register.php', {'username':'Duplicate','email':email_a,'password':password,'confirm_password':password})[0] == 422)
check('SQL injection cannot bypass sign-in', anon.post('login.php', {'email':"' OR '1'='1",'password':password})[0] == 422)
for client, email, name in [(a,email_a,'QA_A'),(b,email_b,'QA_B')]:
    before = next(iter(client.jar)).value
    status, _, _, url = client.post('login.php', {'email':email,'password':password})
    check('Login ' + name, status == 200 and url.endswith('/dashboard.php'))
    check('Session ID regenerated for ' + name, next(iter(client.jar)).value != before)
good = {'title':'Scholarship Application','document_type':'Academic','description':'Requirements for AY 2026–2027','confidentiality':'Private'}
check('Create rejects absent CSRF', a.request('add_document.php', good)[0] == 403)
for label, field, value in [('blank title','title',''),('oversized title','title','x'*121),('oversized description','description','x'*1001),('invalid type','document_type','Injected'),('invalid confidentiality','confidentiality','Everyone')]:
    bad = dict(good); bad[field] = value
    check('Server rejects ' + label, a.post('add_document.php', bad)[0] == 422)
status, body, _, url = a.post('add_document.php', good)
record_id = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)['id'][0]
check('Create and view owned record', status == 200 and good['title'] in body)
check('Search finds owned record', good['title'] in a.request('documents.php?q=Scholarship')[1])
check('Other user search cannot see record', good['title'] not in b.request('documents.php?q=Scholarship')[1])
for route in ['view_document.php','edit_document.php','confirm_delete.php']:
    check('Ownership enforced on ' + route, b.request(route + '?id=' + record_id)[0] == 404)
bad_edit = dict(good, id=record_id, title='Stolen')
check('Cross-user update rejected', b.post('edit_document.php', bad_edit, 'add_document.php')[0] == 404)
check('Cross-user delete rejected', b.post('delete_document.php', {'id':record_id}, 'documents.php')[0] == 404)
check('DELETE via GET rejected', a.request('delete_document.php?id=' + record_id)[0] == 405)
check('Invalid CSRF rejected on update', a.request('edit_document.php', dict(good, id=record_id, csrf='bad'))[0] == 403)
check('Invalid CSRF rejected on delete', a.request('delete_document.php', {'id':record_id,'csrf':'bad'})[0] == 403)
for value in ['-1','0','1 OR 1=1','1.0','99999999999']:
    check('Invalid record ID rejected: ' + value, a.request('view_document.php?id=' + urllib.parse.quote(value))[0] == 400)
check('Array ID rejected safely', a.request('view_document.php?id%5B%5D=1')[0] == 400)
xss = '<script>alert("XSS")</script>'
status, body, _, _ = a.post('edit_document.php', dict(good, id=record_id, title=xss), 'edit_document.php?id=' + record_id)
check('Owned edit succeeds and HTML is encoded', status == 200 and '&lt;script&gt;' in body and xss not in body)
check('Search treats SQL injection as literal data', a.request('documents.php?q=' + urllib.parse.quote("' OR 1=1 --"))[0] == 200)
check('Invalid filter rejected', a.request('documents.php?level=Unknown')[0] == 400)
check('Search length limited', a.request('documents.php?q=' + 'a'*121)[0] == 400)
check('Audit trail shows successful record changes', 'document updated' in a.request('activity.php')[1].lower())
for private_path in ['backend/config/local.php','database/schema.sql','views/document_form.php','backend/controllers/dashboard.php']:
    check('Private resource blocked: ' + private_path, anon.request(private_path)[0] in (403,404))
check('Delete requires explicit confirmation page', 'Yes, delete document' in a.request('confirm_delete.php?id=' + record_id)[1])
check('Owner can delete with valid CSRF', a.post('delete_document.php', {'id':record_id}, 'confirm_delete.php?id=' + record_id)[0] == 200)
check('Deleted record becomes unavailable', a.request('view_document.php?id=' + record_id)[0] == 404)
check('Logout via GET rejected', a.request('logout.php')[0] == 405)
check('Logout rejects missing CSRF', a.request('logout.php', {})[0] == 403)
status, _, _, url = a.post('logout.php', {}, 'dashboard.php')
check('POST logout ends session', status == 200 and url.endswith('/login.php') and a.request('dashboard.php')[3].endswith('/login.php'))
for i in range(12):
    status = anon.post('login.php', {'email':'unknown_' + stamp + '@example.test','password':'wrong-password'})[0]
check('Repeated login failures throttled', status == 429)
output = Path(__file__).parent / 'results.json'
output.write_text(json.dumps({'results':results,'qa_emails':[email_a,email_b]}, indent=2))
print(f'\n{len(results)} integration checks passed. Results: {output}')

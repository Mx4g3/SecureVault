"""HTTP verification gating tests; SMTP success is simulated only for a reserved QA account."""
import http.cookiejar,json,re,subprocess,time,urllib.request,urllib.error,urllib.parse
from pathlib import Path
from otp_fixture import prepare_code
jar=http.cookiejar.CookieJar()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def request(path,data=None):
    try: r=client.open('http://127.0.0.1:8085/'+path,urllib.parse.urlencode(data).encode() if data is not None else None)
    except urllib.error.HTTPError as e:r=e
    return r.status,r.read().decode(),r.url
def token(path):return re.search('name="csrf" value="([a-f0-9]+)"',request(path)[1]).group(1)
def post(path,data,source=None):return request(path,dict(data,csrf=token(source or path)))
results={}
def check(name,condition):
    results[name]=bool(condition);print(('PASS ' if condition else 'FAIL ')+name,flush=True);assert condition,name
email='qa_a_'+str(time.time_ns())+'@example.test'
status,body,url=post('register.php',dict(username='OTP_HTTP',email=email,password='TestVault!98765',confirm_password='TestVault!98765'))
check('Registration requires verification',status==200 and url.endswith('/verify_email.php'))
check('No verification code exposed in HTML','654321' not in body)
check('Pending account cannot access dashboard',request('dashboard.php')[2].endswith('/login.php'))
check('Pending account cannot download a file',request('download.php?id=3')[2].endswith('/login.php'))
check('Verification rejects missing CSRF',request('verify_email.php',dict(code='654321'))[0]==403)
check('Resend rejects missing CSRF',request('verify_email.php',dict(action='resend'))[0]==403)
check('Failed delivery cannot be guessed through',post('verify_email.php',dict(code='654321'))[0]==422)
check('Resend cooldown enforced',post('verify_email.php',dict(action='resend'))[0]==429)
prepare_code(email)
check('Wrong code is rejected via HTTP',post('verify_email.php',dict(code='000000'))[0]==422)
old=next(iter(jar)).value
check('Correct code opens dashboard',post('verify_email.php',dict(code='654321'))[2].endswith('/dashboard.php'))
check('Session rotated after code verification',old!=next(iter(jar)).value)
post('logout.php',{},'dashboard.php')
status,body,url=post('login.php',dict(email=email,password='TestVault!98765'))
check('Verified account still needs code on next login',url.endswith('/verify_email.php'))
check('Password alone cannot access documents',request('documents.php')[2].endswith('/login.php'))
prepare_code(email)
check('Second sign-in code completes authentication',post('verify_email.php',dict(code='654321'))[2].endswith('/dashboard.php'))
Path(__file__).with_name('verification-http-results.json').write_text(json.dumps(results,indent=2))
print(f'{len(results)} HTTP verification checks passed. SMTP was not contacted successfully.')

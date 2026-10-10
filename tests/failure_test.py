"""Verify generic errors using a separate local server with an intentionally invalid DB port."""
import http.cookiejar
import json
import re
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
page = client.open('http://127.0.0.1:8086/login.php').read().decode()
token = re.search(r'name="csrf" value="([a-f0-9]+)"', page).group(1)
data = urllib.parse.urlencode({'email':'demo@securevault.test','password':'not-used','csrf':token}).encode()
try:
    response = client.open('http://127.0.0.1:8086/login.php', data)
except urllib.error.HTTPError as error:
    response = error
body = response.read().decode()
passed = response.status == 500 and 'We couldn’t complete that request.' in body and all(secret not in body for secret in ['SQLSTATE','PDOException','Stack trace','C:\\','securevault_app'])
Path(__file__).with_name('failure-results.json').write_text(json.dumps({'Database unavailable returns generic HTTP 500 without implementation details':passed},indent=2))
assert passed
print('PASS Database unavailable returns generic HTTP 500 without implementation details')

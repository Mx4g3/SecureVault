"""Local end-to-end attachment checks using disposable accounts and synthetic files."""
from otp_fixture import prepare_code
import base64, hashlib, http.cookiejar, io, json, re, time, urllib.request, urllib.error, urllib.parse, uuid, zipfile
from pathlib import Path
BASE = 'http://127.0.0.1:8085/'
results=[]
class Client:
    def __init__(self):
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self, path, data=None, headers=None):
        request=urllib.request.Request(BASE+path,data,headers or {})
        try: response=self.opener.open(request,timeout=30)
        except urllib.error.HTTPError as error: response=error
        return response.status,response.read(),response.headers,response.url
    def token(self, path):
        code,body,_,_=self.request(path)
        assert code==200,(path,code)
        return re.search(rb'name="csrf" value="([a-f0-9]+)"',body).group(1).decode()
    def form(self,path,data,token_page):
        return self.request(path,urllib.parse.urlencode(dict(data,csrf=self.token(token_page))).encode())
    def upload(self,path,data,name=None,content=None,token_page='add_document.php',csrf=True):
        data=dict(data)
        if csrf:data['csrf']=self.token(token_page)
        boundary=uuid.uuid4().hex
        parts=[]
        for key,value in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        if name is not None:parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="attachment"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+content+b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        return self.request(path,b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary})
def check(name,value):
    results.append({'test':name,'passed':bool(value)})
    print(('PASS ' if value else 'FAIL ')+name,flush=True)
    assert value,name
def office(extension):
    path,kind={'docx':('word/document.xml','wordprocessingml.document'),'pptx':('ppt/presentation.xml','presentationml.presentation'),'xlsx':('xl/workbook.xml','spreadsheetml.sheet')}[extension]
    output=io.BytesIO()
    with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml',f'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/{path}" ContentType="application/vnd.openxmlformats-officedocument.{kind}.main+xml"/></Types>')
        z.writestr(path,'<?xml version="1.0"?><test>Fixture</test>')
    return output.getvalue()
stamp=str(time.time_ns()); a,b,anon=Client(),Client(),Client()
for client,kind in [(a,'a'),(b,'b')]:
    email=f'qa_{kind}_{stamp}@example.test'
    status,*_=client.form('register.php',dict(username='Upload_QA',email=email,password='TestVault!98765',confirm_password='TestVault!98765'),'register.php');assert status==200
    prepare_code(email)
    status,*_=client.form('verify_email.php',dict(action='verify',code='654321'),'verify_email.php');assert status==200
fields=dict(title='Upload QA record',document_type='Academic',description='Disposable fixture',confidentiality='Private')
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n'
status,body,_,url=a.upload('add_document.php',fields,'sample.pdf',pdf)
check('PDF upload creates a record',status==200 and b'sample.pdf' in body)
record_id=urllib.parse.parse_qs(urllib.parse.urlparse(url).query)['id'][0]
status,content,headers,_=a.request('download.php?id='+record_id)
check('Owner downloads exact original bytes',status==200 and content==pdf)
check('Download is attachment, no-store, and nosniff',headers.get('Content-Disposition','').startswith('attachment;') and headers.get('Content-Type')=='application/octet-stream' and headers.get('X-Content-Type-Options')=='nosniff' and 'no-store' in headers.get('Cache-Control',''))
check('Another user cannot download',b.request('download.php?id='+record_id)[0]==404)
check('Anonymous download redirects to sign-in',anon.request('download.php?id='+record_id)[3].endswith('/login.php'))
check('Upload without CSRF rejected',a.upload('add_document.php',fields,'sample.pdf',pdf,csrf=False)[0]==403)
for name,content in [('shell.php',b'<?php echo 1;'),('fake.pdf',b'not a PDF'),('fake.png',pdf),('empty.txt',b''),('bad.docx',b'not a zip'),('renamed.pptx',office('docx')),('payload.svg',b'<svg></svg>'),('oversized.pdf',pdf+b' '*(10*1024*1024))]:
    check('Reject '+name,a.upload('add_document.php',fields,name,content)[0]==422)
edit='edit_document.php?id='+record_id
payload=dict(fields,id=record_id)
check('Cross-user replacement denied',b.upload('edit_document.php',payload,'replacement.txt',b'Replacement')[0]==404)
for extension in ['docx','pptx','xlsx']:
    code,body,_,_=a.upload('edit_document.php',payload,'sample.'+extension,office(extension),edit)
    check('Validated '+extension+' package accepted',code==200 and ('sample.'+extension).encode() in body)
for name,content in [('notes.txt',b'Simple text notes'),('table.csv',b'Name,Value\nExample,42\n'),('image.png',base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jH1sAAAAASUVORK5CYII='))]:
    code,_,_,_=a.upload('edit_document.php',payload,name,content,edit)
    check(name+' replacement downloaded byte-for-byte',code==200 and a.request('download.php?id='+record_id)[1]==content)
before=a.request('download.php?id='+record_id)[1]
check('Invalid replacement rejected',a.upload('edit_document.php',payload,'bad.pdf',b'Invalid',edit)[0]==422)
check('Old attachment preserved on invalid replacement',a.request('download.php?id='+record_id)[1]==before)
check('Replacement and removal conflict rejected',a.upload('edit_document.php',dict(payload,remove_attachment='1'),'new.txt',b'New',edit)[0]==422)
check('Remove attachment succeeds',a.upload('edit_document.php',dict(payload,remove_attachment='1'),token_page=edit)[0]==200)
check('Removed attachment cannot be downloaded',a.request('download.php?id='+record_id)[0]==404)
boundary_pdf=pdf+b' '*(10*1024*1024-len(pdf))
check('Exact 10 MB file accepted',a.upload('edit_document.php',payload,'boundary.pdf',boundary_pdf,edit)[0]==200)
check('Large download matches checksum',hashlib.sha256(a.request('download.php?id='+record_id)[1]).digest()==hashlib.sha256(boundary_pdf).digest())
for i in range(9):
    assert a.upload('add_document.php',dict(fields,title='Quota fixture '+str(i)),'quota.pdf',boundary_pdf)[0]==200
check('100 MB per-account boundary accepted',True)
check('Upload above account quota rejected',a.upload('add_document.php',dict(fields,title='Quota overflow unique'),'extra.txt',b'Extra')[0]==422)
check('Quota rejection rolls back new record',b'<h2>0 documents</h2>' in a.request('documents.php?q=Quota+overflow+unique')[1])
check('Replacement excludes previous file from quota',a.upload('edit_document.php',payload,'same-size.pdf',boundary_pdf,edit)[0]==200)
check('Smaller replacement releases storage',a.upload('edit_document.php',payload,'smaller.txt',b'Smaller',edit)[0]==200 and a.upload('add_document.php',dict(fields,title='Space released'),'extra.txt',b'Extra')[0]==200)
check('Request over 12 MB returns 413',a.upload('edit_document.php',payload,'huge.pdf',pdf+b' '*(12*1024*1024),edit)[0]==413)
check('Delete record with attachment succeeds',a.form('delete_document.php',{'id':record_id},'confirm_delete.php?id='+record_id)[0]==200)
check('Deleted attachment no longer accessible',a.request('download.php?id='+record_id)[0]==404)
Path(__file__).with_name('upload-results.json').write_text(json.dumps(results,indent=2))
print(f'{len(results)} upload checks passed. No external data used.')

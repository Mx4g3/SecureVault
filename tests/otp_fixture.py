"""Simulates SMTP acceptance only for disposable QA identities in the isolated local DB."""
import subprocess
from pathlib import Path
def prepare_code(email):
    subprocess.run([r'C:\xampp\php\php.exe',str(Path(__file__).with_name('prepare_otp.php')),email],check=True,capture_output=True)

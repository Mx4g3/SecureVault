<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../securevault/database/connection.php';
require __DIR__ . '/../securevault/backend/includes/functions.php';
require __DIR__ . '/../securevault/backend/includes/verification.php';
$_SESSION=[]; $_SERVER['REMOTE_ADDR']='verification-cli-fixture';
$email='qa_a_' . hrtime(true) . '@example.test';
query('INSERT INTO users (username,email,password_hash) VALUES (?,?,?)',['OTP_QA',$email,password_hash('TestVault!98765',PASSWORD_DEFAULT)]);
$uid=(int)db()->lastInsertId(); $checks=[]; $captured='';
function verify_check(string $name,bool $ok): void { global $checks; $checks[$name]=$ok; if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; }
$capture=function($to,$subject,$body) use (&$captured): bool { preg_match('/code is: ([0-9]{6})/',$body,$match); $captured=$match[1]; return true; };
$id=issue_email_code($uid,$capture);
$row=query('SELECT * FROM email_challenges WHERE id=?',[$id])->fetch();
verify_check('Only a password hash of the code is stored', $row['code_hash']!==$captured && password_verify($captured,$row['code_hash']));
$limited=false; try { issue_email_code($uid,$capture); } catch (DomainException $e) { $limited=true; }
verify_check('Immediate resend is throttled',$limited);
verify_check('Wrong account cannot redeem',!redeem_email_code($id,$captured,$uid+99999));
verify_check('Wrong code is rejected',!redeem_email_code($id,'000000',$uid));
verify_check('Correct code verifies account',redeem_email_code($id,$captured,$uid));
verify_check('Verified email is persisted',(bool)query('SELECT user_id FROM verified_emails WHERE user_id=?',[$uid])->fetch());
verify_check('Code cannot be replayed',!redeem_email_code($id,$captured,$uid));
function age_codes(int $uid):void { query('UPDATE email_challenges SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 MINUTE) WHERE user_id=?',[$uid]); }
age_codes($uid); $expired=issue_email_code($uid,$capture); query('UPDATE email_challenges SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$expired]);
verify_check('Expired code rejected',!redeem_email_code($expired,$captured,$uid));
age_codes($uid); $locked=issue_email_code($uid,$capture);
for($i=0;$i<5;$i++) redeem_email_code($locked,'000000',$uid);
verify_check('Five wrong attempts exhaust code',!redeem_email_code($locked,$captured,$uid));
age_codes($uid); $old=issue_email_code($uid,$capture); $oldCode=$captured;
age_codes($uid); $new=issue_email_code($uid,$capture);
verify_check('Resend invalidates previous code',!redeem_email_code($old,$oldCode,$uid));
verify_check('Newest code works',redeem_email_code($new,$captured,$uid));
age_codes($uid); $failed=false;
try { issue_email_code($uid,static fn()=>false); } catch(DomainException $e) { $failed=true; }
$failedId=$_SESSION['email_challenge'];
verify_check('Delivery failure remains unredeemable',$failed && !redeem_email_code($failedId,'654321',$uid));
query('UPDATE email_challenges SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE) WHERE user_id=?',[$uid]);
$limited=false; try { issue_email_code($uid,$capture); } catch(DomainException $e) { $limited=true; }
verify_check('Hourly send budget is enforced',$limited);
file_put_contents(__DIR__.'/verification-results.json',json_encode($checks,JSON_PRETTY_PRINT));
echo count($checks)." verification checks passed. Mail transport was captured in memory only.\n";

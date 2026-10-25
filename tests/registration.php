<?php
require __DIR__ . '/../admin/src/Service/Comparison.php';
require __DIR__ . '/../admin/src/Service/Registration.php';
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function rejects(callable $fn) {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Invalid selection accepted');
}
$key = Registration::key('nuevo');
$otherKey = Registration::key('otro');
$none = fn($s) => false;
$selection = Registration::select(['nuevo'], [$key=>'1'], [$key=>'nuevo@example.com'], $none, $none);
check($selection === [['username'=>'nuevo','email'=>'nuevo@example.com']], 'Only approved unknown name');
check(Registration::select(['nuevo'], [], [$key=>'invalid'], $none,$none) === [], 'Unchecked name ignored');
rejects(fn()=>Registration::select(['nuevo'], [$key=>'1'], [$key=>''], $none,$none));
rejects(fn()=>Registration::select(['nuevo'], [$otherKey=>'1'], [$otherKey=>'x@example.com'], $none,$none));
rejects(fn()=>Registration::select(['nuevo','otro'], [$key=>'1',$otherKey=>'1'], [$key=>'x@example.com',$otherKey=>'X@example.com'], $none,$none));
rejects(fn()=>Registration::select(['nuevo'], [$key=>'1'], [$key=>'x@example.com'], fn($s)=>true,$none));
rejects(fn()=>Registration::select(['nuevo'], [$key=>'1'], [$key=>'x@example.com'], $none,fn($s)=>true));
$unknown = []; $json = [];
for ($i=0; $i<1800; $i++) {
    $name = 'user'.$i; $unknown[]=$name;
    $json[Registration::key($name)] = ['checked'=>true,'email'=>$name.'@example.com'];
}
$draft = Registration::draft(json_encode($json),$unknown);
check(count($draft['checked'])===1800 && count($draft['emails'])===1800,'1800 rows in one form field');
rejects(fn()=>Registration::draft('not json',[]));
rejects(fn()=>Registration::draft('{"forged":{"checked":true,"email":"x@example.com"}}',[]));

class NewUserDouble {
    public static array $data=[];
    public int $id=99;
    public function bind($data) { self::$data[]=$data; return true; }
    public function save() { return true; }
}
$result=Registration::create([
    ['username'=>'a','email'=>'a@example.com'],['username'=>'b','email'=>'b@example.com']
],fn()=>new NewUserDouble(),2,32);
check(count($result['created'])===2 && !$result['errors'],'Create selected accounts');
check($result['created'][0]['password']!==$result['created'][1]['password'],'Different random passwords');
foreach (NewUserDouble::$data as $data) {
    check($data['groups']===[2] && $data['requireReset']===1 && $data['block']===0,'Registered only, require password change');
    check(strlen($data['password'])>=32 && preg_match('/[a-z]/',$data['password']) && preg_match('/[A-Z]/',$data['password'])
        && preg_match('/[0-9]/',$data['password']) && preg_match('/[^a-zA-Z0-9]/',$data['password']),'Password policy');
}
$failed=Registration::create($selection,fn()=>new class {
    public function bind($data) { return false; }
},2);
check(!$failed['created'] && count($failed['errors'])===1 && !isset($failed['errors'][0]['password']),'Safe partial failure');
echo "OK: selected registrations, email validation, 1800 rows and unique passwords\n";

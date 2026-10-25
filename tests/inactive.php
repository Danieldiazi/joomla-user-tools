<?php
require __DIR__.'/../admin/src/Service/InactiveSelection.php';
require __DIR__.'/../admin/src/Service/InactivityPolicy.php';
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
function check($ok,$why){if(!$ok)throw new RuntimeException($why);}
check(InactiveSelection::decode('[2,4,4]',[2,3,4])===[2,4],'Selected IDs only, deduplicated');
check(InactiveSelection::eligibleIds([2,4],[2,3,4],[3,4,5])===[4],'Only selected, previewed and still eligible accounts');
check(InactiveSelection::eligibleIds([], [2], [2])===[],'No implicit selections');
foreach(['[999]','["2"]','{}','invalid'] as $bad){
    try{InactiveSelection::decode($bad,[2,3]);throw new RuntimeException('Invalid selection accepted');}
    catch(InvalidArgumentException $e){}
}
$ids=range(1,1800);
check(count(InactiveSelection::decode(json_encode($ids),$ids))===1800,'1800 selections in one field');
$cutoff=InactivityPolicy::cutoff(365,new DateTimeImmutable('2026-10-25 12:00:00',new DateTimeZone('UTC')));
$u=(object)['id'=>1,'block'=>0,'lastvisitDate'=>null,'registerDate'=>'2026-10-25 11:00:00'];
check(!InactivityPolicy::eligible($u,$cutoff,true),'Newly registered user never qualifies as one year inactive');
check(str_contains(InactivityPolicy::omissionReason($u,false),'desactivada'),'Never logged in disabled reason');
check(str_contains(InactivityPolicy::omissionReason($u,true),'registro'),'Recent registration reason');
echo "OK: manual inactivity selection, revalidation, 1800 rows and omission reasons\n";

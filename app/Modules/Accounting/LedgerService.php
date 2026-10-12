<?php
declare(strict_types=1);
namespace ReadyFinance;
/** A paged journal ledger; filtering never changes the underlying running balance. */
final class LedgerService {
 public function __construct(private Core $c){}
 public function view(int $h,array $p):array {
  $c=$this->c;$kind=$p['kind']??'ledger';if(!in_array($kind,['money','ledger'],true))throw new Fault('Choose an account ledger.');
  if($kind==='money'){$money=$c->scope('accounts',(string)($p['uuid']??''),$h);$account=$c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$money['ledger_id']]);}
  else $account=$c->scope('ledger_accounts',(string)($p['uuid']??''),$h);
  if((int)($account['is_group']??0))throw new Fault('Choose a posting subaccount for its ledger.');
  $earliest=$c->query('SELECT MIN(j.effective_date) FROM journal_lines l JOIN journal_entries j ON j.id=l.journal_id AND j.household_id=l.household_id WHERE l.household_id=? AND l.ledger_id=?',[$h,$account['id']])->fetchColumn();
  $from=Core::date(($p['from']??'')?:($earliest?:$c->today($h)));$to=Core::date(($p['to']??'')?:$c->today($h));if($from>$to)throw new Fault('Start date must be before end date.');
  $search=trim((string)($p['search']??''));if(mb_strlen($search)>120)throw new Fault('Use at most 120 search characters.');
  $order=$p['order']??'newest';if(!in_array($order,['newest','oldest'],true))throw new Fault('Choose newest or oldest first.');
  if(!preg_match('/^[1-9][0-9]{0,8}$/D',(string)($p['page']??'1')))throw new Fault('Choose a valid page.');$page=(int)($p['page']??1);$size=50;
  $editing=(new AccountManagementService($c))->ready();$columns=$editing?'t.replaces_id,t.edit_reason,':"NULL replaces_id,NULL edit_reason,";
  $base="SELECT t.uuid,t.type,t.description,t.status,t.row_version,$columns t.reversal_of,j.effective_date,j.id journal_id,SUM(l.debit) debit,SUM(l.credit) credit FROM journal_lines l JOIN journal_entries j ON j.id=l.journal_id AND j.household_id=l.household_id JOIN transactions t ON t.id=j.transaction_id AND t.household_id=j.household_id WHERE l.household_id=? AND l.ledger_id=? AND j.effective_date>=? AND j.effective_date<=? GROUP BY t.id,j.id";
  $args=[$h,$account['id'],$from,$to];$filter=' WHERE LOCATE(LOWER(?),LOWER(b.description))>0';
  // All journal rows, including corrections, determine balance before the search filter.
  $opening=(string)$c->query('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM journal_lines l JOIN journal_entries j ON j.id=l.journal_id AND j.household_id=l.household_id WHERE l.household_id=? AND l.ledger_id=? AND j.effective_date<?',[$h,$account['id'],$from])->fetchColumn();
  $totals=$c->one('SELECT COUNT(*) entries,COALESCE(SUM(b.debit),0) debit,COALESCE(SUM(b.credit),0) credit FROM ('.$base.') b',$args);
  $count=(int)$c->query('SELECT COUNT(*) FROM ('.$base.') b'.$filter,[...$args,$search])->fetchColumn();$pages=max(1,(int)ceil($count/$size));$page=min($page,$pages);$offset=($page-1)*$size;
  $running='SELECT b.*,CAST(? AS DECIMAL(38,6))+SUM(b.debit-b.credit) OVER(ORDER BY b.effective_date,b.journal_id ROWS UNBOUNDED PRECEDING) running_balance FROM ('.$base.') b';
  $direction=$order==='newest'?'DESC':'ASC';$rows=$c->all('SELECT b.* FROM ('.$running.') b'.$filter.' ORDER BY b.effective_date '.$direction.',b.journal_id '.$direction.' LIMIT '.$size.' OFFSET '.$offset,[$opening,...$args,$search]);
  $closing=bcadd($opening,bcsub($totals['debit'],$totals['credit'],6),6);
  $chart=(new ChartService($c))->ready();$options=$c->all('SELECT l.uuid,l.name,l.class'.($chart?',l.account_kind,l.is_group':'').($editing?',l.active':'').' FROM ledger_accounts l WHERE l.household_id=?'.($chart?' AND l.is_group=0':'').' ORDER BY l.class,l.name,l.id',[$h]);
  return ['account'=>$account,'from'=>$from,'to'=>$to,'search'=>$search,'order'=>$order,'opening'=>$opening,'closing'=>$closing,'debit'=>$totals['debit'],'credit'=>$totals['credit'],'entries'=>(int)$totals['entries'],'matched'=>$count,'page'=>$page,'pages'=>$pages,'page_size'=>$size,'rows'=>$rows,'accounts'=>$options];
 }
}

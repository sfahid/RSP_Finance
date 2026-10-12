<?php
declare(strict_types=1);
namespace ReadyFinance;
final class StatementReader {
 public static function read(string $path,string $name,string $sheet=''):array {
  $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  if(!is_file($path)||filesize($path)>5000000)throw new Fault('Choose a statement smaller than 5 MB.');
  if(!in_array($ext,['csv','xlsx','xls','pdf'],true))throw new Fault('Choose CSV, XLSX, XLS or a readable PDF.');
  if($ext==='csv'){$rows=self::csv(file_get_contents($path));$sheets=['CSV'];$sheet='CSV';}
  elseif($ext==='pdf'){$rows=self::pdf($path);$sheets=['PDF'];$sheet='PDF';}
  else {
   if(!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class))throw new Fault('The private vendor libraries are missing. Upload the complete update.');
   foreach(['zip','dom','xml','xmlreader','xmlwriter','simplexml','iconv','gd'] as $extension)if(!extension_loaded($extension))throw new Fault('Enable PHP extension '.$extension.' for Excel statement imports.');
   if($ext==='xlsx')self::zipGuard($path);
   $type=\PhpOffice\PhpSpreadsheet\IOFactory::identify($path,['Xlsx','Xls']);
   $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReader($type);$info=$reader->listWorksheetInfo($path);
   $sheets=array_column($info,'worksheetName');$sheet=$sheet?:($sheets[0]??'');
   $chosen=null;foreach($info as $item)if($item['worksheetName']===$sheet)$chosen=$item;
   if(!$chosen||$chosen['totalRows']>2030||$chosen['totalColumns']>40)throw new Fault('Choose a sheet with at most 2,030 rows and 40 columns.');
   $reader->setLoadSheetsOnly([$sheet]);$reader->setReadDataOnly(true);
   $book=$reader->load($path);$ws=$book->getSheetByName($sheet);$rows=[];
   foreach($ws->getRowIterator() as $row){$values=[];foreach($row->getCellIterator() as $cell){$v=$cell->getValue();if($cell->getDataType()==='f')throw new Fault('Formula cells are not accepted. Export statement values to CSV first.');$values[]=is_float($v)?rtrim(rtrim(sprintf('%.10F',$v),'0'),'.'):(string)($v??'');}$rows[]=$values;}
   $book->disconnectWorksheets();
  }
  $rows=array_values(array_filter($rows,fn($r)=>count(array_filter($r,fn($v)=>trim((string)$v)!==''))>0));
  if(count($rows)<2||count($rows)>2030)throw new Fault('Statement needs a header and between 1 and 2,000 transaction rows.');
  $header=0;$best=0;
  foreach(array_slice($rows,0,30) as $i=>$r){$score=0;foreach($r as $v)if(in_array(self::key($v),['date','transactiondate','postingdate','description','narration','transactionreference','transactionnarrative','debit','debitamount','credit','creditamount','amount'],true))$score++;if($score>$best){$best=$score;$header=$i;}}
  if($best<2)throw new Fault('A transaction header could not be identified. Export CSV with Date, Description, Debit, Credit and Balance columns.');
  $headers=array_map(fn($v)=>mb_substr(trim((string)$v),0,100),$rows[$header]);$body=array_slice($rows,$header+1);
  if(count($body)>2000)throw new Fault('Split statements into files of at most 2,000 rows.');
  foreach($body as &$r){$r=array_map(fn($v)=>mb_substr(trim((string)$v),0,1200),$ext==='pdf'?$r:array_pad($r,count($headers),''));}unset($r);
  $data=['headers'=>$headers,'rows'=>$body,'sheet'=>$sheet,'sheets'=>$sheets,'mapping'=>self::map($headers),'date_format'=>'dmy'];
  if(strlen(json_encode($data,JSON_THROW_ON_ERROR))>2000000)throw new Fault('Statement text is too large. Split the file.');return $data;
 }
 public static function key(mixed $v):string{return preg_replace('/[^a-z0-9]/','',strtolower((string)$v));}
 public static function map(array $headers):array {
  $aliases=['date'=>['date','transactiondate','postingdate','valuedate'],'description'=>['description','narration','narrative','transactionreference','details','particulars'],'reference'=>['reference','transactionid','ref','transactionnarrative'],'debit'=>['debit','debitamount','withdrawal','withdrawals','paidout'],'credit'=>['credit','creditamount','deposit','deposits','paidin'],'amount'=>['amount','transactionamount'],'balance'=>['balance','runningbalance','closingbalance'],'direction'=>['drcr','debitcredit','direction','type']];$map=[];
  foreach($aliases as $field=>$names){$map[$field]=-1;foreach($headers as $i=>$v)if(in_array(self::key($v),$names,true)){$map[$field]=$i;break;}}return $map;
 }
 private static function csv(string $text):array {
  if(str_starts_with($text,"\xFF\xFE")||str_starts_with($text,"\xFE\xFF"))$text=mb_convert_encoding($text,'UTF-8','UTF-16');
  $text=preg_replace('/^\xEF\xBB\xBF/','',$text);if(!mb_check_encoding($text,'UTF-8'))throw new Fault('Save the CSV as UTF-8 first.');
  $first=strtok($text,"\r\n");$delimiter=',';$best=0;foreach([',',';',"\t"] as $d){$n=count(str_getcsv($first,$d,'"',''));if($n>$best){$best=$n;$delimiter=$d;}}
  $stream=fopen('php://temp','r+');fwrite($stream,$text);rewind($stream);$rows=[];while(($r=fgetcsv($stream,0,$delimiter,'"',''))!==false){$rows[]=$r;if(count($rows)>2030)throw new Fault('CSV exceeds 2,030 rows.');}fclose($stream);return $rows;
 }
 private static function zipGuard(string $path):void {
  $z=new \ZipArchive();if($z->open($path)!==true)throw new Fault('Invalid XLSX archive.');$total=0;
  try{if($z->numFiles>1500)throw new Fault('Workbook has too many archive entries.');for($i=0;$i<$z->numFiles;$i++){$s=$z->statIndex($i);$total+=$s['size'];if($total>50000000||$s['size']>20000000||($s['size']>1000000&&$s['size']/max(1,$s['comp_size'])>200))throw new Fault('Workbook expands beyond the import limit.');if(str_contains(strtolower($s['name']),'vbaproject'))throw new Fault('Macro workbooks are not accepted.');}}finally{$z->close();}
 }
 private static function pdf(string $path):array {
  if(!class_exists(\Smalot\PdfParser\Parser::class))throw new Fault('Upload the private PDF reader libraries first.');
  $config=new \Smalot\PdfParser\Config();$config->setRetainImageContent(false);$config->setDecodeMemoryLimit(20000000);
  $pdf=(new \Smalot\PdfParser\Parser([],$config))->parseFile($path);if(count($pdf->getPages())>40)throw new Fault('Split PDFs into at most 40 pages.');
  $text=$pdf->getText();if(strlen($text)>2000000)throw new Fault('PDF text is too large.');if(mb_strlen(trim($text))<40)throw new Fault('This PDF appears scanned. OCR is not enabled; use the bank CSV/Excel export.');
  $lines=preg_split('/\R/u',$text);$rows=[];
  foreach($lines as $line){$cells=str_contains($line,"\t")?explode("\t",trim($line,"\r\n")):preg_split('/ {2,}/u',trim($line));if(count($cells)>=4||preg_match('/^(?:\d{1,2}[-\/]\d{1,2}[-\/]\d{4}|\d{4}-\d{2}-\d{2})\b/',trim($line)))$rows[]=$cells;}
  foreach($rows as $row){$map=self::map($row);if($map['date']>=0&&($map['debit']>=0||$map['credit']>=0||$map['amount']>=0))return $rows;}
  // Explicit DR/CR lines are safe to stage; ambiguous blank money columns are never guessed.
  $rows=[['Date','Description','Amount','DRCR','Balance']];
  foreach($lines as $line){if(preg_match('/^(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})\s+(.+?)\s+([\d,]+\.\d{2})\s*(DR|CR)\s+([\d,]+\.\d{2})\s*$/i',trim($line),$m))$rows[]=[$m[1],$m[2],$m[3],$m[4],$m[5]];elseif(preg_match('/^(?:\d{1,2}[-\/]\d{1,2}[-\/]\d{4}|\d{4}-\d{2}-\d{2})\b/',trim($line)))$rows[]=[trim($line)];}
  if(count($rows)<2)throw new Fault('PDF columns could not be read reliably. Use CSV/Excel, or a text PDF with explicit DR/CR amounts. Nothing was posted.');return $rows;
 }
 public static function date(string $value,string $format):string {
  $value=trim($value);
  if(is_numeric($value)&&preg_match('/^\d{5}(?:\.0+)?$/',$value)){$d=\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value);return Core::date($d->format('Y-m-d'));}
  $formats=$format==='mdy'?['m/d/Y','m-d-Y','m/d/y']:['d/m/Y','d-m-Y','d.m.Y','d/m/y','d-M-Y','d M Y'];array_unshift($formats,'Y-m-d');
  foreach($formats as $f){$d=\DateTimeImmutable::createFromFormat('!'.$f,$value);$e=\DateTimeImmutable::getLastErrors();if($d&&(!$e||!$e['warning_count']&&!$e['error_count']))return $d->format('Y-m-d');}throw new Fault('Invalid date; choose day/month or month/day correctly.');
 }
 public static function amount(string $value,bool $signed=false):?string {
  $v=trim($value);if($v===''||in_array($v,['-','—'],true))return null;$negative=false;
  if(preg_match('/^\((.*)\)$/',$v,$m)){$v=$m[1];$negative=true;}if(str_starts_with($v,'-')){$negative=true;$v=substr($v,1);}if($negative&&!$signed)throw new Fault('Debit/credit columns must contain positive amounts.');
  if(str_contains($v,',')){if(!preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,6})?$/D',$v))throw new Fault('Invalid grouping in amount.');$v=str_replace(',','',$v);}
  $n=Core::money($v,true);return $negative?bcsub('0',$n,6):$n;
 }
}

$path = 'C:\Users\deyaa_h\OneDrive - miyahuna.com.jo\codex_ERP\takepos\api\v1\products.php'  
$lines = Get-Content -Path $path 
$result = foreach ($line in $lines) { 
        \"if ($barcode !== '') $sql .= ' AND (p.barcode = ' . chr(39) . $db->escape($barcode) . chr(39) . ' OR EXISTS (SELECT 1 FROM ' . TakeposProductBarcodeService::table() . ' pb WHERE pb.fk_product = p.rowid AND pb.entity = ' . $entity . ' AND pb.barcode = ' . chr(39) . $db->escape($barcode) . chr(39) . '))';\" 

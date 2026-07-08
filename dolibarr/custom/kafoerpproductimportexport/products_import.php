<?php
/* Copyright (C) 2026 */

require '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'html.form.class.php';
require_once DOL_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'product.class.php';
require_once DOL_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'categories' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'categorie.class.php';
require_once DOL_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'stock' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'entrepot.class.php';
require_once DOL_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'files.lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'KafoProductImportHelper.class.php';

$langs->loadLangs(array('products', 'stocks', 'other', 'kafoerpproductimportexport@kafoerpproductimportexport'));

if (empty($user->rights->kafoerpproductimportexport->read)) {
    accessforbidden();
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');

$expectedHeader = array(
    'ref',
    'label',
    'barcode',
    'price_ht',
    'tva_tx',
    'qty',
    'category_ref',
    'warehouse_ref',
    'image',
    'description',
);

$importReport = array();

if ($action === 'downloadtemplate') {
    if (empty($user->rights->kafoerpproductimportexport->export)) {
        accessforbidden();
    }

    $fileName = 'products_template_' . dol_print_date(dol_now(), '%Y%m%d_%H%M%S') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    if ($out) {
        fputcsv($out, $expectedHeader);
        fputcsv($out, array('10001', 'Coca Cola 330ml', '625100001', '0.350', '16', '120', 'DRINKS', 'MAIN', '10001.jpg', 'Can'));
        fputcsv($out, array('10002', 'Pepsi 330ml', '625100002', '0.350', '16', '100', 'DRINKS', 'MAIN', '10002.jpg', 'Can'));
        fclose($out);
    }

    exit;
}

if ($action === 'importzip') {
    if (empty($user->rights->kafoerpproductimportexport->import)) {
        accessforbidden();
    }

    if (empty($_FILES['zipfile']) || !is_array($_FILES['zipfile'])) {
        setEventMessages($langs->trans('KafoErrorZipFileMissing'), null, 'errors');
    } else {
        $upload = $_FILES['zipfile'];
        $originalName = !empty($upload['name']) ? dol_sanitizeFileName((string) $upload['name']) : '';
        $tmpPath = !empty($upload['tmp_name']) ? (string) $upload['tmp_name'] : '';
        $uploadError = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

        if ($uploadError !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
            setEventMessages($langs->trans('KafoErrorZipUploadFailed'), null, 'errors');
        } else {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension !== 'zip') {
                setEventMessages($langs->trans('KafoErrorZipOnlyAllowed'), null, 'errors');
            } elseif (!class_exists('ZipArchive')) {
                setEventMessages($langs->trans('KafoErrorZipExtensionMissing'), null, 'errors');
            } else {
                $baseTmpDir = KafoProductImportHelper::buildPath(DOL_DATA_ROOT, 'kafoerpproductimportexport', 'tmp');
                $runDir = KafoProductImportHelper::buildPath(
                    $baseTmpDir,
                    'import_' . dol_print_date(dol_now(), '%Y%m%d_%H%M%S') . '_' . mt_rand(1000, 9999)
                );
                $extractDir = KafoProductImportHelper::buildPath($runDir, 'extract');

                if (!KafoProductImportHelper::ensureDirectory($baseTmpDir)
                    || !KafoProductImportHelper::ensureDirectory($runDir)
                    || !KafoProductImportHelper::ensureDirectory($extractDir)) {
                    setEventMessages($langs->trans('KafoErrorTmpDirCreateFailed'), null, 'errors');
                } else {
                    $zipPath = KafoProductImportHelper::buildPath($runDir, 'upload.zip');
                    $moveResult = dol_move_uploaded_file($tmpPath, $zipPath, 0, 0, $uploadError);
                    if (empty($moveResult)) {
                        setEventMessages($langs->trans('KafoErrorZipStoreFailed'), null, 'errors');
                    } else {
                        $zip = new ZipArchive();
                        $openResult = $zip->open(dol_osencode($zipPath));
                        if ($openResult !== true) {
                            setEventMessages($langs->trans('KafoErrorZipOpenFailed') . ' (code=' . $openResult . ')', null, 'errors');
                        } else {
                            $extractResult = $zip->extractTo(dol_osencode($extractDir));
                            $zip->close();

                            if (!$extractResult) {
                                setEventMessages($langs->trans('KafoErrorZipExtractFailed'), null, 'errors');
                            } else {
                                $csvPath = KafoProductImportHelper::findProductsCsv($extractDir);
                                $imagesDir = KafoProductImportHelper::findImagesDirectory($extractDir);

                                if ($csvPath === '') {
                                    setEventMessages($langs->trans('KafoErrorProductsCsvMissing'), null, 'errors');
                                } else {
                                    $handle = fopen(dol_osencode($csvPath), 'r');
                                    if (!$handle) {
                                        setEventMessages($langs->trans('KafoErrorProductsCsvOpenFailed'), null, 'errors');
                                    } else {
                                        $header = fgetcsv($handle, 0, ',');
                                        if (!is_array($header)) {
                                            setEventMessages($langs->trans('KafoErrorProductsCsvEmpty'), null, 'errors');
                                        } else {
                                            $header = array_map(static function ($value) {
                                                return trim((string) $value);
                                            }, $header);

                                            if ($header !== $expectedHeader) {
                                                setEventMessages(
                                                    $langs->trans('KafoErrorCsvHeaderInvalid') . ' ' . implode(',', $expectedHeader),
                                                    null,
                                                    'errors'
                                                );
                                            } else {
                                                $lineNumber = 1;

                                                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                                                    $lineNumber++;

                                                    if (!is_array($row)) {
                                                        continue;
                                                    }

                                                    $assoc = KafoProductImportHelper::rowToAssoc($expectedHeader, $row);
                                                    if (empty($assoc)) {
                                                        $importReport[] = array(
                                                            'line' => $lineNumber,
                                                            'ref' => '',
                                                            'label' => '',
                                                            'status' => 'ERROR',
                                                            'message' => $langs->trans('KafoErrorCsvRowInvalid'),
                                                        );
                                                        continue;
                                                    }

                                                    $isEmptyLine = true;
                                                    foreach ($assoc as $cell) {
                                                        if (trim((string) $cell) !== '') {
                                                            $isEmptyLine = false;
                                                            break;
                                                        }
                                                    }
                                                    if ($isEmptyLine) {
                                                        continue;
                                                    }

                                                    $ref = trim((string) $assoc['ref']);
                                                    $label = trim((string) $assoc['label']);
                                                    $barcode = trim((string) $assoc['barcode']);
                                                    $priceHt = KafoProductImportHelper::parseDecimal($assoc['price_ht']);
                                                    $tvaTx = KafoProductImportHelper::parseDecimal($assoc['tva_tx']);
                                                    $qty = KafoProductImportHelper::parseDecimal($assoc['qty']);
                                                    $categoryRef = trim((string) $assoc['category_ref']);
                                                    $warehouseRef = trim((string) $assoc['warehouse_ref']);
                                                    $imageValue = trim((string) $assoc['image']);
                                                    $description = trim((string) $assoc['description']);

                                                    if ($ref === '' || $label === '') {
                                                        $importReport[] = array(
                                                            'line' => $lineNumber,
                                                            'ref' => $ref,
                                                            'label' => $label,
                                                            'status' => 'ERROR',
                                                            'message' => $langs->trans('KafoErrorRefLabelRequired'),
                                                        );
                                                        continue;
                                                    }

                                                    if (KafoProductImportHelper::productRefExists($db, (int) $conf->entity, $ref)) {
                                                        $importReport[] = array(
                                                            'line' => $lineNumber,
                                                            'ref' => $ref,
                                                            'label' => $label,
                                                            'status' => 'SKIPPED',
                                                            'message' => $langs->trans('KafoSkippedProductAlreadyExists'),
                                                        );
                                                        continue;
                                                    }

                                                    if ($barcode !== '' && KafoProductImportHelper::barcodeExists($db, (int) $conf->entity, $barcode)) {
                                                        $importReport[] = array(
                                                            'line' => $lineNumber,
                                                            'ref' => $ref,
                                                            'label' => $label,
                                                            'status' => 'ERROR',
                                                            'message' => $langs->trans('KafoErrorBarcodeAlreadyExists'),
                                                        );
                                                        continue;
                                                    }

                                                    $categoryId = 0;
                                                    if ($categoryRef !== '') {
                                                        $categoryId = KafoProductImportHelper::findCategoryId($db, (int) $conf->entity, $categoryRef);
                                                        if ($categoryId <= 0) {
                                                            $importReport[] = array(
                                                                'line' => $lineNumber,
                                                                'ref' => $ref,
                                                                'label' => $label,
                                                                'status' => 'ERROR',
                                                                'message' => $langs->trans('KafoErrorCategoryNotFound', $categoryRef),
                                                            );
                                                            continue;
                                                        }
                                                    }

                                                    $warehouseId = 0;
                                                    if ($qty > 0) {
                                                        if ($warehouseRef === '') {
                                                            $importReport[] = array(
                                                                'line' => $lineNumber,
                                                                'ref' => $ref,
                                                                'label' => $label,
                                                                'status' => 'ERROR',
                                                                'message' => $langs->trans('KafoErrorWarehouseRequiredForStock'),
                                                            );
                                                            continue;
                                                        }
                                                        $warehouseId = KafoProductImportHelper::findWarehouseId($db, (int) $conf->entity, $warehouseRef);
                                                        if ($warehouseId <= 0) {
                                                            $importReport[] = array(
                                                                'line' => $lineNumber,
                                                                'ref' => $ref,
                                                                'label' => $label,
                                                                'status' => 'ERROR',
                                                                'message' => $langs->trans('KafoErrorWarehouseNotFound', $warehouseRef),
                                                            );
                                                            continue;
                                                        }
                                                    }

                                                    $db->begin();

                                                    $messages = array();
                                                    $status = 'OK';
                                                    $error = '';

                                                    $product = new Product($db);
                                                    $product->ref = $ref;
                                                    $product->label = $label;
                                                    $product->description = $description;
                                                    $product->type = Product::TYPE_PRODUCT;
                                                    $product->status = 1;
                                                    $product->status_buy = 1;
                                                    $product->price = $priceHt;
                                                    $product->tva_tx = $tvaTx;

                                                    if ($barcode !== '') {
                                                        $product->barcode = $barcode;
                                                        if (!empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
                                                            $product->barcode_type = (int) $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE;
                                                        }
                                                    }

                                                    $createResult = $product->create($user);
                                                    if ($createResult <= 0) {
                                                        $error = !empty($product->error) ? $product->error : $langs->trans('KafoErrorProductCreateFailed');
                                                    } else {
                                                        $priceResult = $product->updatePrice($priceHt, 'HT', $user, $tvaTx);
                                                        if ($priceResult <= 0) {
                                                            $error = !empty($product->error) ? $product->error : $langs->trans('KafoErrorProductPriceUpdateFailed');
                                                        }
                                                    }

                                                    if ($error === '' && $categoryId > 0) {
                                                        $catResult = $product->setCategories(array($categoryId));
                                                        if ($catResult < 0) {
                                                            $error = !empty($product->error) ? $product->error : $langs->trans('KafoErrorCategoryAssignFailed');
                                                        }
                                                    }

                                                    if ($error === '' && $qty > 0 && $warehouseId > 0) {
                                                        $warehouse = new Entrepot($db);
                                                        if ($warehouse->fetch($warehouseId) <= 0) {
                                                            $error = $langs->trans('KafoErrorWarehouseNotFound', $warehouseRef);
                                                        } else {
                                                            $stockResult = $product->correct_stock($user, (int) $warehouseId, $qty, 0, $langs->trans('KafoInitialStockImport'));

                                                            if ($stockResult < 0) {
                                                                $error = !empty($product->error) ? $product->error : $langs->trans('KafoErrorStockUpdateFailed');
                                                            }
                                                        }
                                                    }

                                                    if ($error === '') {
                                                        $imagePath = KafoProductImportHelper::findImageFile($imagesDir, $imageValue, $ref);
                                                        if ($imagePath !== '') {
                                                            $baseProductDir = !empty($conf->product->multidir_output[$conf->entity])
                                                                ? $conf->product->multidir_output[$conf->entity]
                                                                : KafoProductImportHelper::buildPath(DOL_DATA_ROOT, 'product');

                                                            $productDir = KafoProductImportHelper::buildPath($baseProductDir, dol_sanitizeFileName($product->ref));
                                                            if (!KafoProductImportHelper::ensureDirectory($productDir)) {
                                                                $messages[] = $langs->trans('KafoCreatedImageDirFailed');
                                                            } else {
                                                                $destName = dol_sanitizeFileName(basename($imagePath));
                                                                $destPath = KafoProductImportHelper::buildPath($productDir, $destName);
                                                                $copyOk = @copy(dol_osencode($imagePath), dol_osencode($destPath));
                                                                if ($copyOk) {
                                                                    $messages[] = $langs->trans('KafoCreatedImageAttached');
                                                                } else {
                                                                    $messages[] = $langs->trans('KafoCreatedImageCopyFailed');
                                                                }
                                                            }
                                                        } else {
                                                            $messages[] = $langs->trans('KafoCreatedImageNotFound');
                                                        }
                                                    }

                                                    if ($error !== '') {
                                                        $db->rollback();
                                                        $status = 'ERROR';
                                                        $message = $langs->trans('KafoErrorImportLine') . ': ' . $error;
                                                    } else {
                                                        $db->commit();

                                                        if (empty($messages)) {
                                                            $messages[] = $langs->trans('KafoCreated');
                                                        }
                                                        $message = implode(', ', $messages);
                                                    }

                                                    $importReport[] = array(
                                                        'line' => $lineNumber,
                                                        'ref' => $ref,
                                                        'label' => $label,
                                                        'status' => $status,
                                                        'message' => $message,
                                                    );
                                                }
                                            }
                                        }

                                        fclose($handle);
                                    }
                                }
                            }
                        }
                    }
                }

                KafoProductImportHelper::cleanupDirectory($runDir);
            }
        }
    }
}

llxHeader('', $langs->trans('KafoERPImportExportProductTitle'));

print load_fiche_titre($langs->trans('KafoERPImportExportProductTitle'), '', 'product');

$modulePageUrl = dol_buildpath('/kafoerpproductimportexport/products_import.php', 1);
$templateUrl = $modulePageUrl . '?action=downloadtemplate';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="2">' . $langs->trans('KafoImportExportActions') . '</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('KafoDownloadCsvTemplate') . '</td>';
print '<td class="right">';
if (!empty($user->rights->kafoerpproductimportexport->export)) {
    print '<a class="butAction" href="' . dol_escape_htmltag($templateUrl) . '">' . $langs->trans('Download') . '</a>';
} else {
    print '<span class="opacitymedium">' . $langs->trans('NotEnoughPermissions') . '</span>';
}
print '</td>';
print '</tr>';

print '</table>';
print '</div>';
print '</div>';

print '<div class="fichehalfright">';
print '<div class="info-box">';
print '<strong>' . $langs->trans('KafoZipHelpTitle') . '</strong><br>';
print '<pre style="margin-top:8px;">products.csv' . PHP_EOL;
print 'images' . DIRECTORY_SEPARATOR . PHP_EOL;
print '  10001.jpg' . PHP_EOL;
print '  10002.jpg</pre>';
print '<strong>' . $langs->trans('KafoCsvColumns') . '</strong><br>';
print '<code>' . implode(',', $expectedHeader) . '</code>';
print '</div>';
print '</div>';
print '</div>';

print '<div style="clear:both"></div>';
print '<br>';

print '<form method="POST" action="' . dol_escape_htmltag($modulePageUrl) . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="importzip">';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="2">' . $langs->trans('KafoImportZip') . '</th>';
print '</tr>';
print '<tr class="oddeven">';
print '<td class="fieldrequired" style="width: 25%;">' . $langs->trans('File') . '</td>';
print '<td><input type="file" name="zipfile" accept=".zip" required></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td></td>';
print '<td>';
if (!empty($user->rights->kafoerpproductimportexport->import)) {
    print '<input class="button button-save" type="submit" value="' . dol_escape_htmltag($langs->trans('KafoImportZipButton')) . '">';
} else {
    print '<span class="opacitymedium">' . $langs->trans('NotEnoughPermissions') . '</span>';
}
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

if (!empty($importReport)) {
    print '<br>';
    print load_fiche_titre($langs->trans('KafoImportReport'));
    print '<div class="div-table-responsive">';
    print '<table class="tagtable liste centpercent">';
    print '<tr class="liste_titre">';
    print '<th>' . $langs->trans('KafoLine') . '</th>';
    print '<th>' . $langs->trans('Ref') . '</th>';
    print '<th>' . $langs->trans('Label') . '</th>';
    print '<th>' . $langs->trans('Status') . '</th>';
    print '<th>' . $langs->trans('Message') . '</th>';
    print '</tr>';

    foreach ($importReport as $reportLine) {
        $statusClass = 'badge badge-status4';
        if ($reportLine['status'] === 'OK') {
            $statusClass = 'badge badge-status1';
        } elseif ($reportLine['status'] === 'SKIPPED') {
            $statusClass = 'badge badge-status3';
        }

        print '<tr class="oddeven">';
        print '<td>' . ((int) $reportLine['line']) . '</td>';
        print '<td>' . dol_escape_htmltag($reportLine['ref']) . '</td>';
        print '<td>' . dol_escape_htmltag($reportLine['label']) . '</td>';
        print '<td><span class="' . $statusClass . '">' . dol_escape_htmltag($reportLine['status']) . '</span></td>';
        print '<td>' . dol_escape_htmltag($reportLine['message']) . '</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';
}

llxFooter();
$db->close();

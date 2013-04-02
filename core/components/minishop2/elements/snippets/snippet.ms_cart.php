<?php
/* @var miniShop2 $miniShop2 */
/* @var pdoFetch $pdoFetch */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->initialize($modx->context->key);
if (!empty($modx->services['pdofetch'])) {unset($modx->services['pdofetch']);}
$pdoFetch = $modx->getService('pdofetch','pdoFetch', MODX_CORE_PATH.'components/pdotools/model/pdotools/',$scriptProperties);
$pdoFetch->config['nestedChunkPrefix'] = 'minishop2_';
$pdoFetch->addTime('pdoTools loaded.');

$cart = $miniShop2->cart->get();
if (!empty($_GET['msorder'])) {
	return '';
}
else if (empty($cart)) {
	return !empty($tplEmpty) ? $pdoFetch->getChunk($tplEmpty) : '';
}

// Include TVs
$tvsLeftJoin = '';
$tvsSelect = array();
if (!empty($includeTVs)) {
	$tvs = array_map('trim',explode(',',$includeTVs));
	if(!empty($tvs[0])){
		$q = $modx->newQuery('modTemplateVar', array('name:IN' => $tvs));
		$q->select('id,name');
		if ($q->prepare() && $q->stmt->execute()) {
			$tv_ids = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
			if (!empty($tv_ids)) {
				foreach ($tv_ids as $tv) {
					$tvsLeftJoin .= ',{"class":"modTemplateVarResource","alias":"TV'.$tv['name'].'","on":"TV'.$tv['name'].'.contentid = msProduct.id AND TV'.$tv['name'].'.tmplvarid = '.$tv['id'].'"}';
					$tvsSelect[] = ' "TV'.$tv['name'].'":"TV'.$tv['name'].'.value as '.$tv['name'].'" ';
				}
			}
		}
		$pdoFetch->addTime('Included list of tvs: <b>'.implode(', ',$tvs).'</b>.');
	}
}
// End of including TVs

// Include Thumbnails
$thumbsLeftJoin = '';
$thumbsSelect = array();
if (!empty($includeThumbs)) {
	$thumbs = array_map('trim',explode(',',$includeThumbs));
	if(!empty($thumbs[0])){
		foreach ($thumbs as $thumb) {
			$thumbsLeftJoin .= ',{"class":"msProductFile","alias":"'.$thumb.'","on":"'.$thumb.'.product_id = msProduct.id AND '.$thumb.'.parent != 0 AND '.$thumb.'.path LIKE \'%/'.$thumb.'/\'"}';
			$thumbsSelect[] = ' "'.$thumb.'":"'.$thumb.'.url as '.$thumb.'" ';
		}
		$pdoFetch->addTime('Included list of thumbnails: <b>'.implode(', ',$thumbs).'</b>.');
	}
}
// End of including Thumbnails

// Fields to select
$resourceColumns = !empty($includeContent) ?  $modx->getSelectColumns('msProduct', 'msProduct') : $modx->getSelectColumns('msProduct', 'msProduct', '', array('content'), true);
$dataColumns = $modx->getSelectColumns('msProductData', 'Data', '', array('id'), true);
$vendorColumns = $modx->getSelectColumns('msVendor', 'Vendor', 'vendor.', array('id'), true);

// Tables for joining
$leftJoin = '{"class":"msProductData","alias":"Data","on":"msProduct.id=Data.id"},{"class":"msVendor","alias":"Vendor","on":"Data.vendor=Vendor.id"}';
if (!empty($tvsLeftJoin)) {$leftJoin .= $tvsLeftJoin;}
if (!empty($thumbsLeftJoin)) {$leftJoin .= $thumbsLeftJoin;}
$select = '"msProduct":"'.$resourceColumns.'","Data":"'.$dataColumns.'","Vendor":"'.$vendorColumns.'"';
if (!empty($tvsSelect)) {$select .= ','.implode(',', $tvsSelect);}
if (!empty($thumbsSelect)) {$select .= ','.implode(',', $thumbsSelect);}
$pdoFetch->addTime('Query parameters are prepared.');

// Initializing chunk for template rows
if (!empty($tplRow)) {$pdoFetch->getChunk($tplRow);}

// Working
$outer = array('goods' => '', 'total_count' => 0, 'total_weight' => 0, 'total_cost' => 0);
foreach ($cart as $k => $v) {

	$default = array(
		'class' => 'msProduct'
		,'where' => '{"msProduct.id":"'.$v['id'].'","class_key":"msProduct"}'
		,'leftJoin' => '['.$leftJoin.']'
		,'select' => '{'.$select.'}'
		,'sortby' => 'id'
		,'sortdir' => 'ASC'
		,'groupby' => 'msProduct.id'
		,'fastMode' => false
		,'limit' => 0
		,'return' => 'data'
	);
// Merge all properties and run!
	$pdoFetch->config = array_merge($pdoFetch->config, $default, $scriptProperties);
	$rows = $pdoFetch->run();

	if (!empty($rows[0])) {
		$row = $rows[0];
		$row['key'] = $k;
		$row['count'] = $v['count'];
		$row['price'] = $v['price'];
		$row['weight'] = $v['weight'];
		$row['cost'] = $v['count'] * $v['price'];

		// Additional properties of product
		if (!empty($v['options']) && is_array($v['options'])) {
			foreach ($v['options'] as $key => $value) {
				$row['option.'.$key] = $value;
			}
		}
		unset($v['options']);

		// Processing quick fields
		if (!empty($tplRow)) {
			$pl = $pdoFetch->makePlaceholders($row);
			$qfields = array_keys($pdoFetch->elements[$tplRow]['placeholders']);
			foreach ($qfields as $field) {
				if (!empty($row[$field])) {
					$row[$field] = str_replace($pl['pl'], $pl['vl'], $pdoFetch->elements[$tplRow]['placeholders'][$field]);
				}
				else {
					$row[$field] = '';
				}
			}
		}

		$outer['goods'] .= !empty($tplRow) ? $pdoFetch->getChunk($tplRow, $row) : str_replace(array('[[',']]'),array('&091;&091;','&093;&093;'), print_r($row,1));
		$outer['total_count'] += $v['count'];
		$outer['total_weight'] += $v['count'] * $v['weight'];
		$outer['total_cost'] += $row['cost'];
	}
}

return !empty($tplOuter) ? $pdoFetch->getChunk($tplOuter, $outer) : str_replace(array('[[',']]'),array('&091;&091;','&093;&093;'), print_r($outer,1));
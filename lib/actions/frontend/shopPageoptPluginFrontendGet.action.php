<?php


class shopPageoptPluginFrontendGetAction  extends shopFrontendAction
{
	public function execute()
	{
		$status = shopPageoptPlugin::getStatus();
		if($status){
			wa()->getResponse()->setTitle('Интерфейс оптового покупателя');
			// $model = new shopRegistrypagePluginModel();
			// $registry = $model->getReestr($user_id, true);
			$hash = "";
			$query = waRequest::get('query_my', '');
			if($query && strlen($query) > 2){
				$hash = "search/query=$query";
			}
			$collection = new shopProductsCollection($hash);
			$filter_get = waRequest::get();
			if(isset($filter_get['page'])) unset($filter_get['page']);
			if(isset($filter_get['query_my'])) unset($filter_get['query_my']);
			$collection->filters($filter_get);

			$limit = (int)waRequest::cookie('products_per_page');
	        if (!$limit || $limit < 0 || $limit > 500) {
	            $limit = $this->getConfig()->getOption('products_per_page');
	        }
	        $page = waRequest::get('page', 1, 'int');
	        if ($page < 1) {
	            $page = 1;
	        }
	        $offset = ($page - 1) * $limit;

	        
			$products = $collection->getProducts('*,skus_filtered,skus_image', $offset, $limit);

	        $count = $collection->count();
	        $pages_count = ceil((float)$count / $limit);

	        $this->view->assign('pages_count', $pages_count);
	        $this->view->assign('products_count', $count);


			$user_id = wa()->getUser()->getId();
			$waModel = new waModel();
			$sql = "SELECT `category_id` cid FROM `wa_contact_categories` wcc
				LEFT JOIN `wa_contact_category` wc
				ON wcc.`category_id` = wc.`id`
				WHERE wc.`name` LIKE '%опт%' AND wcc.`contact_id` = $user_id
				ORDER BY wc.`id` LIMIT 1";
			$category_id = $waModel->query($sql)->fetchField("cid");
			$price_id = 0;
			if($category_id){
				$sql = "SELECT price_id FROM `shop_price_params` WHERE `category_id` = $category_id";
				$price_id = $waModel->query($sql)->fetchField("price_id");
				$sql = "SELECT `hash` FROM `shop_easyithelper_getpricelist`
					WHERE `id` = '$price_id'";
				$hash = $waModel->query($sql)->fetchField("hash");
				$this->view->assign('hash', $hash);
				$this->view->assign('price_id', $price_id);
			}
			
			$this->view->assign('products', $products);
			$this->view->assign('query_my', $query);

			$cat_filter = '186,115,119,5,103,price,124,2,4,133';
			$filter_data = waRequest::get();
			$filters = array();
	    	$feature_map = array();
	    	$filter_ids = explode(',', $cat_filter);
	    	$feature_model = new shopFeatureModel();
	    	$features = $feature_model->getById(array_filter($filter_ids, 'is_numeric'));
	    	if ($features) {
	            $features = $feature_model->getValues($features);
	        }
	        $category_value_ids = $collection->getFeatureValueIds(false);
	        foreach ($filter_ids as $fid) {
	            if ($fid == 'price') {
	                $range = $collection->getPriceRange();
	                if ($range['min'] != $range['max']) {
	                    $filters['price'] = array(
	                        'min' => shop_currency($range['min'], null, null, false),
	                        'max' => shop_currency($range['max'], null, null, false),
	                    );
	                }
	            } elseif (isset($features[$fid]) && isset($category_value_ids[$fid])) {
	                //set existing feature code with saved filter id
	                $feature_map[$features[$fid]['code']] = $fid;

	                //set feature data
	                $filters[$fid] = $features[$fid];

	                $min = $max = $unit = null;

	                foreach ($filters[$fid]['values'] as $v_id => $v) {

	                    //remove unused
	                    if (!in_array($v_id, $category_value_ids[$fid])) {
	                        unset($filters[$fid]['values'][$v_id]);
	                    } else {
	                        if ($v instanceof shopRangeValue) {
	                            $begin = $this->getFeatureValue($v->begin);
	                            if (is_numeric($begin) && ($min === null || (float)$begin < (float)$min)) {
	                                $min = $begin;
	                            }
	                            $end = $this->getFeatureValue($v->end);
	                            if (is_numeric($end) && ($max === null || (float)$end > (float)$max)) {
	                                $max = $end;
	                                if ($v->end instanceof shopDimensionValue) {
	                                    $unit = $v->end->unit;
	                                }
	                            }
	                        } else {
	                            $tmp_v = $this->getFeatureValue($v);
	                            if ($min === null || $tmp_v < $min) {
	                                $min = $tmp_v;
	                            }
	                            if ($max === null || $tmp_v > $max) {
	                                $max = $tmp_v;
	                                if ($v instanceof shopDimensionValue) {
	                                    $unit = $v->unit;
	                                }
	                            }
	                        }
	                    }
	                }
	                if (!$filters[$fid]['selectable'] && ($filters[$fid]['type'] == 'double' ||
	                        substr($filters[$fid]['type'], 0, 6) == 'range.' ||
	                        substr($filters[$fid]['type'], 0, 10) == 'dimension.')
	                ) {
	                    if ($min == $max) {
	                        unset($filters[$fid]);
	                    } else {
	                        $type = preg_replace('/^[^\.]*\./', '', $filters[$fid]['type']);
	                        if ($type != 'double') {
	                            $filters[$fid]['base_unit'] = shopDimension::getBaseUnit($type);
	                            $filters[$fid]['unit'] = shopDimension::getUnit($type, $unit);
	                            if ($filters[$fid]['base_unit']['value'] != $filters[$fid]['unit']['value']) {
	                                $dimension = shopDimension::getInstance();
	                                $min = $dimension->convert($min, $type, $filters[$fid]['unit']['value']);
	                                $max = $dimension->convert($max, $type, $filters[$fid]['unit']['value']);
	                            }
	                        }
	                        $filters[$fid]['min'] = $min;
	                        $filters[$fid]['max'] = $max;
	                    }
	                }
	            }
	        }

            if ($filters) {
	            foreach ($filters as $field => $filter) {
	                if (isset($filters[$field]['values']) && (!count($filters[$field]['values']))) {
	                    unset($filters[$field]);
	                }
	            }
	            $this->view->assign('filters', $filters);
	        }
		}
		else{
			throw new waException(_ws('Page not found'), 404);
		}
	}


	protected function getFeatureValue($v)
    {
        if ($v instanceof shopDimensionValue) {
            return $v->value_base_unit;
        }
        if (is_object($v)) {
            return $v->value;
        }
        return $v;
    }
}
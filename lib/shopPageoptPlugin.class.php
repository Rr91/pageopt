<?php

class shopPageoptPlugin extends shopPlugin
{
	public static function getLink($type=0){
		$status = self::getStatus();
		if($type==1 && $status) return '<li class="account__menu-item"><a href="/my/registrypage/">Интерфейс оптового покупателя</a></li>'; 
		if($status) return '<li class="account__sign-in-item"><a href="/my/registrypage/">Интерфейс оптового покупателя</a></li>';
		return '';
	}

	public static function getStatus()
	{
		$use = false;
		$plugin = wa('shop')->getPlugin('pageopt');
		$status = $plugin->getSettings('status');
		$user_id = wa()->getUser()->getId();
		
		if($user_id){
			$model = new waModel();
			$query = "SELECT id FROM `wa_contact_category` WHERE `name` LIKE '%опт%'";
			$cats = array_keys($model->query($query)->fetchAll('id'));
			$query = 'SELECT COUNT(*) cnt FROM `wa_contact_categories` WHERE category_id IN ('.implode(",", $cats).')  AND contact_id = "'.$user_id.'"';
			$use = boolval( $model->query($query)->fetchField('cnt'));
			// waLog::log($user_id, 'xxx.log');
		}

		return $status && $use;
	}

	public static function getStock($pid){
		$query = "SELECT ss.`name`, SUM(sps.`count`) cnt  FROM `shop_product_stocks` sps 
					LEFT JOIN `shop_stock` ss
					ON sps.`stock_id` = ss.`id`
					WHERE sps.`product_id` = '".$pid."' AND  ss.`id` != 2
					GROUP BY ss.`name`";
		$model = new waModel();
		$rs = $model->query($query)->fetchAll('name');
		return $rs;
	}

	public static function getSkus($products){
		$skus = array();
		foreach ($products as $pid => $product) {
			$skus[] = $product['sku_id'];
		}
		if(!$skus) return "";
		$query = 'SELECT id, sku FROM shop_product_skus WHERE id IN ('.implode(",", $skus).')';
		$model = new waModel();
		$skus = $model->query($query)->fetchAll("id", 1);
		return $skus;
	}
}

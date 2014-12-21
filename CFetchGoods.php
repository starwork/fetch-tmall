<?php
// bref: 天猫采集类
// auth:  nl
// date: 20141218
class FetchGoods
{
	//采集代理类
	private $_httpFetch = null;
	
	//分类基础连接
	private $_catUrl = 'http://list.tmall.com/search_product.htm?';
	
	//物品详情url
	private $_goodUrl = 'http://detail.tmall.com/item.htm?id=';
	//采集规则
	private $_rule = array();
	
	public function __construct()
	{
		$this->_rule = array(
                'page_total' => '/class="ui-page-s-len"\>\d+\/(\d+)\<\/b\>/', // 总页数
                'goods_id' => '/class="product.+?data-id="(\d+)"/is', // 商品ID
                'name' => '/\<title\>(.+?)-tmall.+?\<\/title\>/is', // 商品名称
                'price' => '/<em title="([^"]+)">/', // 商品价格
                'intro' => '/\<div\sclass="attributes-list"\sid="J_AttrList"\>(.+?)\<\/div\>\s+\<\/div\>/is', // 简介
                'product_img' => '/(http:\/\/.+?)_60x60q90\.jpg/',
                'desc_url' => '/"(http:\/\/dsc\.taobaocdn\.com\/.+?)"/', // 商品描述
                'shop_intro' => '/class="extend"\>(.+?)\<\/div\>/is', // 店铺简介
            );
    
    // 实例化http采集类
   $this->_httpFetch = new HttpFetch();
	}
	
	//获取分类和品牌下的物品信息
	//@param int $tmCatID 天猫分类ID
	//@param int $tmBrandID 天猫品牌ID
	public function get($tmCatID, $tmBrandID='', $page = 1)
	{
		$goodIDs = $this->getGoods($tmCatID, $tmBrandID, $page);
		if($goodIDs)
		{
			foreach($goodIDs as $var)
			{
				$this->getGoodsDestail($var['id']);
			}
		}
		
	}
	//获取分类和品牌下的物品ID
	//@param int $tmCatID 天猫分类ID
	//@param int $tmBrandID 天猫品牌ID
	public function getGoods($tmCatID, $tmBrandID='', $page = 1)
	{
		$data = array();
		//$url = $this->_catUrl;
		$param = '';
		
		$s = ($page - 1) * 60;
		
		if($tmCatID>0)
		{
			$param .= 'cat='.$tmCatID;
		}
		if($tmBrandID>0)
		{
			$param .= '&brand='.$tmBrandID;
		}
		if($s > 0)
		{
			$param .= '&s='.$s;
		}
		
		if($param == '')
		{
			return null;
		}
		
		$url = $this->_catUrl.$param;
		echo "url:$url.\n";
		
	 $pageSource = $this->_httpFetch->get($url);
	 
	 //echo $pageSource.'<br>';
	 phpQuery::$defaultCharset = 'GBK';
	 phpQuery::newDocument($pageSource);
	
		//all page
   $page = explode('/', trim(pq('.ui-page-s-len')->html()));
   if(count($page) >=2 )
   {
   		$data['pageCount'] = $page[1];
   }
   
   //品牌名
   $brandName = pq('li[data-tag="brand"]')->attr('title');
   //$brandName = mb_convert_encoding(stripcslashes($brandName), 'UTF-8', 'GBK');

   $brandName = str_replace("品牌:", '', $brandName);
   $data['brandName'] = $brandName;
   //$brandName = iconv('gbk', 'utf-8', $brandName);
   phpQuery::$documents = array();
   
   $data['goods'] = $this->_getPageGoodsIDsAndPric($url);
    return $data;
	}
	
	//获取物品的详细信息
	//@param int $goodsID
	public function getGoodsDestail($goodsID=0)
	{
		if($goodsID > 0)
		{
			$url = $this->_goodUrl.$goodsID;
			
			$goodSource = $this->_httpFetch->get($url);
		
      $goodSource = mb_convert_encoding($goodSource, "UTF-8", "GBK");
     
      $rules = $this->_rule;
      
       // 获取商品描述
      $goodsDescUrl = $this->_collectOne($goodSource, $rules['desc_url']); 
      if ($goodsDescUrl)
      {
      		//echo "desc:$goodsDescUrl\n";
      		
          //$goodsDesc = $this->_httpFetch->get($goodsDescUrl);
					$goodsDesc = file_get_contents($goodsDescUrl);
          $goodsDesc = ltrim($goodsDesc, "var desc='");
          $goodsDesc = rtrim($goodsDesc, "';");
          $goodsDesc = str_replace("';", '', $goodsDesc);
          
          $goodsDesc = str_replace('tmall', '6041', $goodsDesc );
					$goodsDesc = str_replace('Tmall', '6041', $goodsDesc );
					$goodsDesc = str_replace('天猫', '至惠商盟', $goodsDesc );
					$goodsDesc = str_replace('淘宝', '至惠商盟', $goodsDesc );
					$goodsDesc = str_replace('\\', '', $goodsDesc);
					$goodsDesc = str_replace('\';', '', $goodsDesc);
          $data['desc'] = base64_encode(mb_convert_encoding(stripcslashes($goodsDesc), 'UTF-8', 'GBK'));
          //echo $data['desc'] = mb_convert_encoding($goodsDesc, 'UTF-8', 'GBK');
          
      }
      
      
      // 匹配商品图片
      //$data['product_img'] = array();
      $data['goods_img'] = $this->_getCollectAll($goodSource, $rules['product_img']);
      
      
      //属性
      phpQuery::$defaultCharset = 'utf8';
   	  phpQuery::newDocument($goodSource);
   	  
   	  $dls = pq('.tb-prop');

   	  $propty = array();
   	  if($dls)
   	  {
   	  	foreach($dls as $dl)
   	  	{
   	  		if(!$dl)
   	  		{
   	  			break;
   	  		}
   	  		
   	  		$lis = pq($dl)->find('ul>li');
   	  		foreach($lis as $li)
   	  		{
   	  		
   	  			$dataKey = pq($li)->attr('data-value');
   	  			
   	  			$dataVar = pq($li)->find('span')->html();
   	  			
   	  			$keys = explode(':', $dataKey);
   	  			
   	  			if(count($keys) >= 2 )
   	  			{
   	  				$propty[$keys[0]][$keys[1]] = $dataVar;
   	  			}
   	  		}
   	  	}
   	  }
   	  
   	  $data['propty'] = $propty;
   	  //var_dump($data);
      return $data;
		}
		return array();
	}
	
  
  //获取站点采集规则
  //@param string $name 某个站点规则名称
  //@return string|array 指定规则名称返回规则字符串，否返回某个站点规则
   
	private function _getRule($name = '')
	{
		return ($name) ? $this->_rule[$name] : $this->_rule;
	}
	
	//获取页面的物品id
	//$param string $url 天猫页面url
	//$return array|bool 返回物品ID and price
	private function _getPageGoodsIDsAndPric($url)
	{
		$pageSource = $this->_httpFetch->get($url);
		
		// 获取商品ID 采集规则
		//$rule = $this->_getRule('goods_id');
		
		// 该页没有找到商品，采集完成
    if (false !== strpos($pageSource, '<p class="item-not-found">'))
    {
        break;
    }
    
    phpQuery::$defaultCharset = 'GBK';
    phpQuery::newDocument($pageSource);
    
    //writeFile("testcat.txt", $pageSource);
    $goodsDiv = pq('.product');
    if(!$goodsDiv)
    {
    	break;
    }
    
    $goods = array();
    foreach($goodsDiv as $div)
    {
    	$data = array();
    	 //$img_url = pq($li)->find('div[class="pic"]')->find('a')->attr('href');
      $data['price'] = pq($div)->find('.productPrice')->find('em')->attr('title');
    	
    	$data['id'] = pq($div)->attr('data-id');
    	
    	$data['title'] = mb_convert_encoding(pq($div)->find('.productTitle')->find('a')->html(), 'UTF-8', 'GBK');
    	
    	if($data['id'])
    	{
    		$goods[] = $data;
    	}
    }
	
		phpQuery::$documents = array();
   return $goods;
	}
	
	//匹配多条数据
	//@param string $pageSource 页面html
	//@param rule string $rule 匹配规则
	//@return array | 
	private function _getCollectAll(&$source, $rule)
	{
		if (preg_match_all($rule, $source, $matches))
		{
            return $matches[1];
    }
    return array();
	}
	
	// 匹配一条数据
  //@param string $source 网页源码
  //@param string $preg 采集规则
  //@return string|bool 成功返回结果，失败返回false
  private function _collectOne(&$source, $preg) 
  {
      if (preg_match($preg, $source, $matches))
      {
          return trim($matches[1]);
      }
      return false;
  }
	
}
?>
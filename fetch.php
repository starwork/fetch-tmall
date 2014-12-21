<?php
//通过天猫分类id、品牌id采集
include 'phpQuery/phpQuery.php';
include 'HttpFetch.class.php';
include 'CFetchGoods.php';

$catID = 0; //天猫分类ID
$brand = 0; //天猫品牌ID
$page = 0;// 页数

$paraCount = count($argv);
if( $paraCount < 2)
{
	echo "param input error\n";
	exit;
}
else if($paraCount == 2)
{
	$catID = $argv[1];
}
else if($paraCount == 3)
{
	$catID = $argv[1];
	$brandID = $argv[2];
}
else
{
	$catID = $argv[1];
	$brandID = $argv[2];
	$page = $argv[3];
}

// 执行商品采集
$fetchGoods = new FetchGoods();

echo utf82gbk("采集...\n");

$goodList = $fetchGoods->getGoods($catID, $brandID, $page);
if($goodList && $goodList['goods'])
{
	echo utf82gbk("品牌：").utf82gbk($goodList['brandName'])."\n";
	echo utf82gbk("总页数:").$goodList['pageCount']."\n";
	foreach($goodList['goods'] as $key => $good)
	{
		echo utf82gbk("[$key]".$good['title'].", |".$good['price']."\n");
		
		//暂时不显示详情数据
		$goodList['goods'][$key]['more'] = $fetchGoods->getGoodsDestail($good['id']);
	}
}


function utf82gbk($str)
{
	return iconv('utf-8', 'gbk',$str);
}
?>
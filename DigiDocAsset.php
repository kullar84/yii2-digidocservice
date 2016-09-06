<?php
/**
 * @copyright 2016 5D Vision
 * @link http://www.5dvision.ee
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

/**
 * DigiDocAsset.php
 *
 * @package  digipeegel\models\Schools
 * @author Kullar Kert <kullar@5dvision.ee>
 * @copyright 2016 5D Vision
 */

namespace kullar84\digidocservice;

use yii\web\AssetBundle;

class DigiDocAsset extends AssetBundle
{

	public $sourcePath = '@vendor/kullar84/yii2-digidocservice/assets/';
	public $js = [
		"js/hwcrypto.js",
		"js/hashcode.js",
	];
	public $depends = [
		'yii\web\YiiAsset',
	];

}

<?php
/**
 * @copyright 2016 5D Vision
 * @link http://www.5dvision.ee
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

namespace kullar84\digidocservice;

use Yii;
use yii\web\HttpException;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use kullar84\digidocservice\helpers\DocHelper;
use kullar84\digidocservice\helpers\CertificateHelper;

/**
 * DigiDoc is a storage for DigidDoc Service methods
 */
class DigiDoc extends DigiDocService
{

	/**
	 * DigoDocService configuration options.
	 *
	 * @var array
	 */
	public $options = [];
	private $_docHelper;

	/**
	 * Init this component.
	 */
	public function init()
	{
		parent::init();

		if (!$this->options['new_file_type']) {
			throw new InvalidConfigException('You must set your digidocservice new_file_type.');
		}

		$directory = Yii::getAlias($this->options['upload_directory']);

		if (is_null($directory)) {
			throw new InvalidConfigException('You must set your digidocservice upload_directory.');
		}

		if (!is_dir($directory)) {
			FileHelper::createDirectory($directory);
		}
		$this->registerDigiDocAsset();
	}

	public function registerDigiDocAsset()
	{
		$view = Yii::$app->controller->getView();

		DigiDocAsset::register($view);
	}

	public function getDocHelper()
	{
		if (!is_object($this->_docHelper)) {
			$this->_docHelper = DocHelper::newInstance($this->options);
		}

		return $this->_docHelper;
	}

	/**
	 * Helper method for getting the DigiDocService session code from HTTP session.
	 *
	 * @return string - Session code of the current DigiDocService session.
	 * @throws Exception - It is expected that if this method is called then dds session is started and session code is
	 *                     loaded to HTTP session. If it is not so then an exception is thrown.
	 */
	public function getDdsSession()
	{
		if (!Yii::$app->session->has('ddsSessionCode')) {
			throw new HttpException(400, 'There is no active session with DDS.');
		}

		return Yii::$app->session->get('ddsSessionCode');
	}

	public function startDdsSession()
	{
		$this->killDdsSession();
	}

	/**
	 * Check if there is open session then try to close it
	 *
	 * @param $dds
	 *
	 * @throws Exception
	 */
	public function killDdsSession()
	{
		if (Yii::$app->session->has('ddsSessionCode')) {
			// If the session data of previous dds session still exists we will initiate a cleanup.
			FileHelper::removeDirectory($this->get_upload_directory());

			$sessionCode = $this->getDdsSession();

			try {
				$this->CloseSession(array('Sesscode' => $sessionCode));
				$this->debug_log('DDS session \'' . $sessionCode . '\' closed.');
			} catch (Exception $e) {
				$this->debug_log('Closing DDS session ' . $sessionCode . ' failed.');
			}
		}
		DocHelper::get_hashcode_session()->end(); // End the Hashcode container session.
	}

	/**
	 * Logging helper method. Logging that is done through this method can be turned of by setting the variable logging_on to FALSE.
	 *
	 * @param $message - Message to be logged.
	 */
	public function debug_log($message)
	{
		if ($this->options['logging_on']) {
			Yii::trace('[' . (isset($_REQUEST['requestId']) ? $_REQUEST['requestId'] : '') . '] [' . Yii::$app->session->id . '] ' . $message, 'digidoc');
		}
	}

	/**
	 * @return string
	 */
	public function get_upload_directory()
	{
		$dir_path = Yii::getAlias($this->options['upload_directory']) . $this->getDdsSession();

		if (!is_dir($dir_path)) {
			FileHelper::createDirectory($dir_path);
		}

		$this->debug_log("Upload directory: '$dir_path'.");

		return $dir_path;
	}

	/**
	 * Helper method for getting the name of the container currently handled. Used for example at the moment of downloading
	 * the container to restore the original file name.
	 *
	 * @return string - File name of the container in the moment it was uploaded.
	 * @throws Exception - It is expected that if this method is called then dds session is started and the original
	 *                     container name is loaded to HTTP session. If it is not so then an exception is thrown.
	 */
	public function getOriginalContainerName()
	{
		if (!Yii::$app->session->has('originalContainerName')) {
			throw new HttpException(400, 'There is no with files version of container, so the container can not be restored.');
		}
		return Yii::$app->session->get('originalContainerName');
	}

	public function createNewSignedDocContainer($containerName)
	{
		$container_type = DocHelper::get_desired_container_type($this->options['new_file_type']);

		// Start the Session with DDS
		$start_session_response = $this->StartSession(array('bHoldSession' => 'true'));
		$dds_session_code = $start_session_response['Sesscode'];


		// Create an empty container to DDS session.
		$format = $container_type['format'];
		$version = $container_type['version'];
		$container_short_type = $container_type['shortType'];

		// Following 2 parameters are necessary for the next potential requests.
		Yii::$app->session->set('ddsSessionCode', $dds_session_code);
		Yii::$app->session->set('originalContainerName', DocHelper::get_new_container_name($containerName, $container_short_type));

		$this->CreateSignedDoc(array(
			'Sesscode' => $this->getDdsSession(),
			'Format' => $format,
			'Version' => $version
		));

		return $this;
	}

	public function addFile2DocContainer($file)
	{
		// Add data file as HASHCODE to the container in DDS session
		$datafile_mime_type = FileHelper::getMimeType($file);
		DocHelper::add_datafile_via_dds($file, $datafile_mime_type);

		$dds_session_code = $this->getDdsSession();

		// Get the HASHCODE container from DDS
		$get_signed_doc_response = $this->GetSignedDoc(array('Sesscode' => $dds_session_code));

		$container_data = $get_signed_doc_response['SignedDocData'];
		if (strpos($container_data, 'SignedDoc') === false) {
			$container_data = base64_decode($container_data);
		}

		// Create container with datafiles on the local server disk so that there would be one with help of which it is possible
		// to restore the container if download is initiated.
		DocHelper::create_container_with_files($container_data, array(new \SK\Digidoc\FileSystemDataFile($file)));
		
		$this->debug_log("Container created, datafile added and session started with hashcode form of container. DDS session ID: '$dds_session_code'.");
	}
	
	public function addExtraFile2DocContainer($file)
	{
		$datafile_mime_type = FileHelper::getMimeType($file);
		DocHelper::add_datafile_via_dds($file, $datafile_mime_type);

		$dds_session_code = $this->getDdsSession();

		// Get the HASHCODE container from DDS
		$get_signed_doc_response = $this->GetSignedDoc(array('Sesscode' => $dds_session_code));

		$container_data = $get_signed_doc_response['SignedDocData'];
		if (strpos($container_data, 'SignedDoc') === false) {
			$container_data = base64_decode($container_data);
		}
		
		// Merge previously added datafiles to an array with the new datafile.
        $datafiles = DocHelper::get_datafiles_from_container();
        array_push($datafiles, new \SK\Digidoc\FileSystemDataFile($file));

		// Create container with datafiles on the local server disk so that there would be one with help of which it is possible
		// to restore the container if download is initiated.
		DocHelper::create_container_with_files($container_data, $datafiles);
		
		$this->debug_log("Extra datafile added. DDS session ID: '$dds_session_code'.");
	}

	/**
	 * Create signature hash
	 * 
	 * @param array $post Post array
	 * 
	 * @return array Response 
	 * 
	 * @throws \Exception
	 */
	public function idSignCreateHash($post)
	{
		$response = [];
		try {
			$this->debug_log('User started the preparation of signature with ID Card to the container.');

			if (!isset($post['signersCertificateHEX'])) {
				throw new \Exception('There were missing parameters which are needed to sign with ID Card.');
			}

			// Let's prepare the parameters for PrepareSignature method.
			$prepare_signature_req_params['Sesscode'] = $this->getDdsSession();
			$prepare_signature_req_params['SignersCertificate'] = $post['signersCertificateHEX'];
			$prepare_signature_req_params['SignersTokenId'] = '';

			if (isset($post['signersRole'])) {
				$prepare_signature_req_params['Role'] = $post['signersRole'];
			}
			if (isset($post['signersCity'])) {
				$prepare_signature_req_params['City'] = $post['signersCity'];
			}
			if (isset($post['signersState'])) {
				$prepare_signature_req_params['State'] = $post['signersState'];
			}
			if (isset($post['signersPostalCode'])) {
				$prepare_signature_req_params['PostalCode'] = $post['signersPostalCode'];
			}
			if (isset($post['signersCountry'])) {
				$prepare_signature_req_params['Country'] = $post['signersCountry'];
			}
			$prepare_signature_req_params['SigningProfile'] = '';

			// Invoke PrepareSignature.
			$prepare_signature_response = $this->PrepareSignature($prepare_signature_req_params);

			// If we reach here then everything must be OK with the signature preparation.
			$response['signature_info_digest'] = $prepare_signature_response['SignedInfoDigest'];
			$response['signature_id'] = $prepare_signature_response['SignatureId'];
			$response['signature_hash_type'] = CertificateHelper::getHashType($response['signature_info_digest']);
			$response['is_success'] = true;
		} catch (Exception $e) {
			$code = $e->getCode();
			$message = (!!$code ? $code . ': ' : '') . $e->getMessage();
			$this->debug_log($message);
			$response['error_message'] = $message;
		}

		return $response;
	}
}

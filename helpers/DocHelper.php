<?php

namespace kullar84\digidocservice\helpers;

use Yii;
use yii\helpers\FileHelper;
use SK\Digidoc\Digidoc;
use SK\Digidoc\BdocContainer;
use SK\Digidoc\DdocContainer;

/**
 * Helper class for dealing with conversion and manipulation of digital document files.
 *
 * LICENSE:
 *
 * This library is free software; you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General
 * Public License as published by the Free Software Foundation;
 * either version 2.1 of the License, or (at your option) any
 * later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @author        Tarmo Kalling <tarmo.kalling@nortal.com>
 * @author        Kullar Kert <kullar@5dvision.ee>
 * @license       http://www.opensource.org/licenses/lgpl-license.php LGPL
 */
class DocHelper
{

	public static $options;

	/**
	 * In here the SOAP Client is initiated for communication with DigiDocService.
	 */
	public function __construct($options)
	{
		self::$options = $options;
	}

	/**
	 * This method is the only way to get an instance of DocHelper.
	 *
	 * @return DocHelper
	 */
	public static function newInstance($options)
	{
		static $inst = null;
		if ($inst === null) {
			$inst = new DocHelper($options);
		}

		return $inst;
	}

	/**
	 * Helper method for fetching the uploaded container from PHP $_FILES array. If the uploaded container is BDOC
	 * then the contents of the container are Base64 encoded.
	 *
	 * @param string $container_upload_input_name - Name of the input used to upload the container.
	 *
	 * @return string                      - Contents of the hashcode container file.
	 */
	public static function get_encoded_hashcode_version_of_container($container_upload_input_name)
	{
		$hashcode_session = self::get_hashcode_session();
		$doc = $hashcode_session->containerFromString(file_get_contents($_FILES[$container_upload_input_name]['tmp_name']));
		$hashcode_contents = $doc->toHashcodeFormat()->toString();
		if ($doc->getContainerFormat() == 'BDOC 2.1') {
			$hashcode_contents = base64_encode($hashcode_contents);
		}

		return $hashcode_contents;
	}

	/**
	 * Returns the hashcode container session and if there is none then initiates one.
	 *
	 * @return \SK\Digidoc\DigidocSession
	 */
	public static function get_hashcode_session()
	{
		if (!isset($_REQUEST['hashcodeSession'])) {
			self::setHashcodeSession();
		}

		return $_REQUEST['hashcodeSession'];
	}

	/**
	 * Method for converting container in hashcode form to a container with files.
	 *
	 * @param $container_data - Contents of the container. If container type is BDOC then container_data should be
	 *                          Base64 encoded.
	 * @param $datafiles - Array of FileSystemDataFile instances
	 *
	 * @return string         - Path to the created container
	 */
	public static function create_container_with_files($container_data, $datafiles)
	{
		$path_to_created_doc = Yii::$app->digidocservice->get_upload_directory() . DIRECTORY_SEPARATOR . Yii::$app->digidocservice->getOriginalContainerName();
		$hashcode_session = self::get_hashcode_session();
		$hashcode_container = $hashcode_session->containerFromString($container_data);
		$with_files_container_contents = $hashcode_container->toDatafilesFormat($datafiles)->toString();
		FileHelper::removeDirectory($path_to_created_doc);
		$handler = fopen($path_to_created_doc, 'w');
		fwrite($handler, $with_files_container_contents);
		fclose($handler);

		return $path_to_created_doc;
	}

	/**
	 * Updates the container in DDS session with a new datafile.
	 *
	 * @param $path_to_datafile - Path to where the datafile is located
	 * @param $datafile_mime_type - Mime Type of the datafile that is to be added.
	 */
	public static function add_datafile_via_dds($path_to_datafile, $datafile_mime_type)
	{
		$container_type = DocHelper::get_container_type(Yii::$app->digidocservice->getOriginalContainerName());
		$digest_type = $container_type === 'BDOC' ? BdocContainer::DEFAULT_HASH_ALGORITHM : 'sha1';
		$data = file_get_contents($path_to_datafile);
		$filename = basename($path_to_datafile);

		$datafile_id = self::get_next_datafile_id();

		$digest_value = $container_type === 'BDOC' ? BdocContainer::datafileHashcode($data) : DdocContainer::datafileHashcode($filename, $datafile_id, $datafile_mime_type, $data);

		Yii::$app->digidocservice->AddDataFile(array(
			'Sesscode' => Yii::$app->digidocservice->getDdsSession(),
			'FileName' => basename($path_to_datafile),
			'MimeType' => $datafile_mime_type,
			'ContentType' => 'HASHCODE',
			'Size' => filesize($path_to_datafile),
			'DigestType' => $digest_type,
			'DigestValue' => $digest_value
		));
	}

	/**
	 * Method determines the container type with the help of file extension.
	 * One can determine the container type with more foolproof and clever ways if necessary.
	 * For example by looking if the content of the file are XML or something else. As an example this will do.
	 *
	 * @param $filename - Filename of the container which type is to be determined.
	 *
	 * @return string - Type of container(BDOC or DDOC).
	 * @throws Exception - In case the file extension is unknown.
	 */
	public static function get_container_type($filename)
	{
		$extension = strtolower(end(explode('.', $filename)));
		if ($extension === 'bdoc' || $extension === 'asice' || $extension === 'sce') {
			return 'BDOC';
		} elseif ($extension === 'ddoc') {
			return 'DDOC';
		}
		throw new Exception("Unknown container with file extension '$extension'.");
	}

	/**
	 * In case of DDOC, it is important that DataFiles are indexed correctly so this mehtod helps to figure out
	 * what index should the next potential DataFile in container carry.
	 *
	 * @return string - ID of the next potential datafile if one would be added to the container in session.
	 */
	public static function get_next_datafile_id()
	{
		if (!file_exists(Yii::$app->digidocservice->get_upload_directory() . DIRECTORY_SEPARATOR . Yii::$app->digidocservice->getOriginalContainerName())) {
			return 'D0';
		}
		$datafiles = DocHelper::get_datafiles_from_container();
		$no_of_datafiles = count($datafiles);
		if ($no_of_datafiles == 0) {
			return 'D0';
		}

		return 'D' . self::get_first_missing_datafile_no_from_dds();
	}

	/**
	 * Extracts the datafiles from a container.
	 *
	 * @return array - Array of all the FileSystemDataFile instances that the container in session holds.
	 */
	public static function get_datafiles_from_container()
	{
		$original_container_path = Yii::$app->digidocservice->get_upload_directory() . DIRECTORY_SEPARATOR . Yii::$app->digidocservice->getOriginalContainerName();
		$doc = self::get_container_type(Yii::$app->digidocservice->getOriginalContainerName()) == 'BDOC' ? new BdocContainer($original_container_path) : new DdocContainer($original_container_path);

		return $doc->getDataFiles();
	}

	/**
	 * Helper method for determining next potential datafiles index. It looks if there is a datafile missing
	 * from the middle of array and if there is it returns it's index. For example if container has datafiles
	 * with indexes D0, D1 and D3 then it would return 2. If the indexes array is complete and there is nothing missing
	 * then it returns 0.
	 */
	private static function get_first_missing_datafile_no_from_dds()
	{
		$dds = DigiDocService::Instance();
		$sig_doc_info = $dds->GetSignedDocInfo(array('Sesscode' => get_dds_session_code()));
		$document_file_info = $sig_doc_info['SignedDocInfo'];
		if (isset($document_file_info) && isset($document_file_info->DataFileInfo)) {
			if (isset($document_file_info->DataFileInfo->Id)) {
				$data_files = array($document_file_info->DataFileInfo);
			} else {
				$data_files = $document_file_info->DataFileInfo;
			}
			for ($i = 0;; $i++) {
				$found_id = false;
				foreach ($data_files as &$data_file) {
					if ('D' . $i === $data_file->Id) {
						$found_id = true;
						break;
					}
				}
				if (!$found_id) {
					return $i;
				}
			}
		}

		return 0;
	}

	/**
	 * Method does'nt actually remove anything from anywhere. It just returns the datafiles in the session container
	 * except the one that is named.
	 *
	 * @param $to_be_removed_name - Name of the datafile that is to be removed.
	 *
	 * @return array - Datafiles minus the removed one.
	 */
	public static function remove_datafile($to_be_removed_name)
	{
		$datafiles = Doc_Helper::get_datafiles_from_container();
		$i = 0;
		foreach ($datafiles as &$datafile) {
			if ($datafile->getName() == $to_be_removed_name) {
				unset($datafiles[$i]);
			}
			$i++;
		}

		return $datafiles;
	}

	/**
	 * Method gives info about the desired outcome container format
	 *
	 * @param $container_type_input_name - Name of the container type.
	 *
	 * @return array - Specification of the container that was selected as an array.
	 *
	 * @throws Exception - If container type was unknown or unspecified, an Exception is thrown.
	 */
	public static function get_desired_container_type($container_type_input_name)
	{
		$valid_container_types = array('BDOC 2.1', 'DIGIDOC-XML 1.3');

		if (!in_array($container_type_input_name, $valid_container_types)) {
			throw new Exception("Invalid container type '$container_type_input_name'.");
		}
		$parts = explode(' ', $container_type_input_name);
		$short_type = 'BDOC';

		if ($parts[0] === 'DIGIDOC-XML') {
			$short_type = 'DDOC';
		}

		return array(
			'format' => $parts[0],
			'version' => $parts[1],
			'shortType' => $short_type
		);
	}

	/**
	 * This should be called in the end of every request where session with DDS is already started.
	 * It saves the hashcode container session to HTTP session.
	 */
	public static function persist_hashcode_session()
	{
		$hashcode_session = self::get_hashcode_session();
		$_SESSION['hashcodeSession'] = $hashcode_session;
	}

	/**
	 * In the creation of new container there it is named by the first datafile it contains. This helper method
	 * helps to figure out this new containers file name.
	 *
	 * @param $uploaded_file_name - Name of the datafile which gives its name to the container.
	 * @param $container_type - File extension of the container(bdoc or ddoc)
	 *
	 * @return string - Derived name of the container.
	 */
	public static function get_new_container_name($uploaded_file_name, $container_type)
	{
		$position_of_first_dot = strpos($uploaded_file_name, '.');
		$container_type = strtolower($container_type);
		if ($position_of_first_dot === false) {
			return $uploaded_file_name . '.' . $container_type;
		}

		return substr($uploaded_file_name, 0, $position_of_first_dot) . '.' . $container_type;
	}

	public static function setHashcodeSession()
	{
		if (Yii::$app->session->has('hashcodeSession')) {
			$_REQUEST['hashcodeSession'] = Yii::$app->session->get('hashcodeSession');
		} else {
			$ddoc = new Digidoc();
			$_REQUEST['hashcodeSession'] = $ddoc->createSession();
		}
	}
}

<?php

namespace kullar84\digidocservice;

use yii\base\Component;

/**
 * The class through which all of the communication with DigiDocService is done.
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
class DigiDocService extends Component
{

	const RESPONSE_STATUS_OK = 'OK';

	/**
	 * @var array - Different MID status responses and there corresponding error messages as explained in DigiDocServices specification.
	 */
	public $get_mid_status_response_error_messages = array(
		'EXPIRED_TRANSACTION' => 'There was a timeout before the user could sign with Mobile ID.',
		'USER_CANCEL' => 'User has cancelled the signing operation.',
		'NOT_VALID' => 'Signature is not valid.',
		'MID_NOT_READY' => 'Mobile ID is not yet available for this phone. Please try again later.',
		'PHONE_ABSENT' => 'Mobile phone is not reachable.',
		'SENDING_ERROR' => 'The Mobile ID message could not be sent to the mobile phone.',
		'SIM_ERROR' => 'There was a problem with the mobile phones SIM card.',
		'OCSP_UNAUTHORIZED ' => 'Mobile ID user is not authorized to make OCSP requests.',
		'INTERNAL_ERROR ' => 'There was an internal error during signing with Mobile ID.',
		'REVOKED_CERTIFICATE ' => 'The signers certificate is revoked.'
	);

	/**
	 * @var SoapClient - DigiDocService client.
	 */
	private $client;

	/**
	 * Init this component.
	 */
	public function init()
	{
		parent::init();

		//In here the SOAP Client is initiated for communication with DigiDocService.
		$this->client = new \SoapClient(null, array(
			'location' => $this->options['dds_endpoint_url'],
			'uri' => 'http://www.sk.ee/DigiDocService/DigiDocService_2_3.wsdl',
			'use' => SOAP_LITERAL,
			'trace' => true
		));
	}

	public function StartSession($params)
	{
		return $this->invoke('StartSession', $params);
	}

	/**
	 * Helper method through which all invocations of DigiDocService are actually made.
	 *
	 * @param $service_name - Name of the DigiDocServices operation to be invoked.
	 * @param $params - Request parameters for the operation.
	 *
	 * @return mixed - SOAP operation Response.
	 */
	private function invoke($service_name, $params)
	{
		try {
			$response = $this->client->$service_name($this->get_soap_var($params));
			if ($this->options['log_all_dds_requests_responses']) {
				$this->debug_log($service_name . 'Request: \'' . $this->client->__getLastRequest() . '\'');
				$this->debug_log($service_name . 'Response: \'' . $this->client->__getLastResponse() . '\'');
			}

			if ($response === self::RESPONSE_STATUS_OK || $response['Status'] === self::RESPONSE_STATUS_OK) {
				return $response;
			}

			throw new Exception($response['Status']);
		} catch (Exception $e) {
			$this->propagate_soap_exception($e, $service_name);
		}

		return null;
	}

	/**
	 * Helper method for converting a usual array to a SoapVar used by SoapClient.
	 *
	 * @param $data - The usual array which is to be turned to SoapVar
	 *
	 * @return SoapVar - The resulting SoapVar
	 */
	private function get_soap_var(array $data)
	{
		return new \SoapVar($this->get_XML_string($data), XSD_ANYXML);
	}

	/**
	 * Helper method for construction an XML from an array.
	 *
	 * @param $data - The array to be converted to an XML string
	 * @param $result - XML to which the rest of the XML is appended.
	 *
	 * @return string - The resulting XML string.
	 */
	private function get_XML_string(array $data, $result = '')
	{
		foreach ($data as $key => $value) {
			$result .= '<' . $key . '>';
			if (is_array($value)) {
				$result .= $this->get_XML_String($value, $result);
			} else {
				$result .= htmlspecialchars($value);
			}
			$result .= '</' . $key . '>';
		}

		return $result;
	}
	/*
	 *  Rest of the methods are invocations of DDS operations. What each DigiDocService operation does is described in
	 *  DigiDocService's specification at https://www.sk.ee/upload/files/DigiDocService_spec_eng.pdf
	 */

	/**
	 * Helper method for handling Exceptions from invocations of DDS properly.
	 *
	 * @param $e - The Exception from DDS.
	 * @param $service_name - Name of the DigiDocService operation during which the exception occured.
	 *
	 * @throws Exception - The wrapper Exception of the thrown exception.
	 */
	private function propagate_soap_exception($e, $service_name)
	{
		$this->debug_log('Following request caused an error: \'' . $this->client->__getLastRequest() . '\'');
		$detail_message = '';
		if (isset($e->detail) && isset($e->detail->message)) {
			$detail_message = $e->detail->message;
		}
		$code = (!!$e->getCode() ? $e->getCode() . ' - ' : '') . $e->getMessage();

		throw new Exception("There was an error invoking $service_name: " . ($code . (empty($detail_message) ? '' : ' - ' . $detail_message)));
	}

	public function CloseSession($params)
	{
		return $this->invoke('CloseSession', $params);
	}

	public function GetSignedDoc($params)
	{
		return $this->invoke('GetSignedDoc', $params);
	}

	public function GetSignedDocInfo($params)
	{
		return $this->invoke('GetSignedDocInfo', $params);
	}

	public function PrepareSignature($params)
	{
		return $this->invoke('PrepareSignature', $params);
	}

	public function FinalizeSignature($params)
	{
		return $this->invoke('FinalizeSignature', $params);
	}

	public function RemoveSignature($params)
	{
		return $this->invoke('RemoveSignature', $params);
	}

	public function MobileSign($params)
	{
		return $this->invoke('MobileSign', $params);
	}

	public function GetStatusInfo($params)
	{
		return $this->invoke('GetStatusInfo', $params);
	}

	public function AddDataFile($params)
	{
		return $this->invoke('AddDataFile', $params);
	}

	public function RemoveDataFile($params)
	{
		return $this->invoke('RemoveDataFile', $params);
	}

	public function CreateSignedDoc($params)
	{
		return $this->invoke('CreateSignedDoc', $params);
	}
}

<?php

namespace kullar84\digidocservice\exceptions;

use yii\base\Exception;


/**
 * FileException class for dealing file exception .
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
 * @author        Mihkel Selgal <mihkel.selgal@nortal.com>
 * @author        Kullar Kert <kullar@5dvision.ee>
 * @license       http://www.opensource.org/licenses/lgpl-license.php LGPL
 */
class FileException extends Exception
{

    /**
     * @var \yii\httpclient\Response HTTP response instance.
     */
    public $response;


    /**
     * Constructor.
     *
     * @param \yii\httpclient\Response $response response body
     * @param string $message error message
     * @param integer $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($response, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }
}
<?php
/**

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
OF SUCH DAMAGE.

*/

/**
 * A set of utility functions used by the module
 */
class SimpleWikiUtils
{
    public function __construct()
    {
    }
    /**
     * Quote up a filter of the form
     *
     * array ("ParentID =" => 1)
     *
     * @param unknown_type $filter
     * @return unknown_type
     */
    public function quote($filter = array(), $join = " AND ")
    {
        $QUOTE_CHAR = defined('DB::USE_ANSI_SQL') ? '"' : '';

        $string = '';
        $sep = '';

        foreach ($filter as $field => $value) {
            // first break the field up into its two components
            $operator = '';
            if (is_string($field)) {
                list($field, $operator) = explode(' ', trim($field));
            }

            if (is_array($value)) {
                // quote each individual one into a string
                $ins = '';
                $insep = '';
                foreach ($value as $v) {
                    $ins .= $insep . Convert::raw2sql($v);
                    $insep = ',';
                }
                $value = '('.$ins.')';
            } elseif (is_null($value)) {
                $value = 'NULL';
            } elseif (is_string($field)) {
                $value = "'" . Convert::raw2sql($value) . "'";
            }

            if (strpos($field, '.')) {
                list($tb, $fl) = explode('.', $field);
                $string .= $sep . $QUOTE_CHAR . $tb . $QUOTE_CHAR . '.' . $QUOTE_CHAR . $fl . $QUOTE_CHAR . " $operator " . $value;
            } else {
                if (is_numeric($field)) {
                    $string .= $sep . $value;
                } else {
                    $string .= $sep . $QUOTE_CHAR . $field . $QUOTE_CHAR . " $operator " . $value;
                }
            }

            $sep = $join;
        }

        return $string;
    }

    public function log($message, $level=null)
    {
        if (!$level) {
            $level = SS_Log::NOTICE;
        }
        $message = array(
            'errno' => '',
            'errstr' => $message,
            'errfile' => dirname(__FILE__),
            'errline' => '',
            'errcontext' => ''
        );

        SS_Log::log($message, $level);
    }
}

<?php

/*
 * This file is part of the 'org.octris.core' package.
 *
 * (c) Harald Lapp <harald@octris.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace org\octris\core\validate\type {
    /**
     * Validator for testing if a string is a valid email.
     *
     * @octdoc      c:type/email
     * @copyright   copyright (c) 2014 by Harald Lapp
     * @author      Harald Lapp <harald@octris.org>
     */
    class email extends \org\octris\core\validate\type 
    /**/
    {
        /**
         * Validator implementation.
         *
         * @octdoc  m:email/validate
         * @param   mixed       $value          Value to validate.
         * @return  bool                        Returns true if value is valid.
         */
        public function validate($value)
        /**/
        {
            return filter_var($value, FILTER_VALIDATE_EMAIL);
        }
    }
}

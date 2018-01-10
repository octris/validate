<?php

/*
 * This file is part of the 'octris/validate' package.
 *
 * (c) Harald Lapp <harald@octris.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Octris\Validate;

use \Octris\Validate as validate;

/**
 * Validate by providing a validation schema.
 *
 * @copyright   copyright (c) 2010-2018 by Harald Lapp
 * @author      Harald Lapp <harald@octris.org>
 */
class Schema
{
    /**
     * Validation schema.
     *
     * @type    array
     */
    protected $schema = [];

    /**
     * Validation mode.
     *
     * @type    int
     */
    protected $mode;

    /**
     * Fail setting. Whether to fail late or early on validation. Late failing
     * is default. This means, that the validator will try to validate all
     * fields before it returns. With 'fail early' the validator will fail and
     * return on the first invalid field.
     *
     * @type    bool
     */
    protected $fail_early = false;

    /**
     * Whether to validate all values agains the configured charset.
     *
     * @type    bool
     */
    protected $validate_charset = true;

    /**
     * Collected errors.
     *
     * @type    array
     */
    protected $errors = [];

    /**
     * Sanitzed data.
     *
     * @type    array
     */
    protected $data = [];

    /**
     * Whether validation succeeded.
     *
     * @type    bool
     */
    protected $is_valid = false;

    /**
     * Available validation modes:
     *
     * - SCHEMA_STRICT:  fields not in schema will raise a validation error (default)
     * - SCHEMA_CLEANUP: fields not in schema will be removed
     * - SCHEMA_IGNORE:  fields not in schema will be silently ignored
     */
    const SCHEMA_STRICT = 1;
    const SCHEMA_CLEANUP = 2;
    const SCHEMA_IGNORE = 3;

    /**
     * Fail modes.
     */
    const FAIL_LATE = 0;
    const FAIL_EARLY = 8;

    /**
     * Validate characterset
     */
    const VALIDATE_CHARSET = 4;

    /**
     * Default validation mode.
     */
    const DEFAULT_MODE = self::SCHEMA_STRICT | self::VALIDATE_CHARSET;

    /**
     * Constructor.
     *
     * @param   array       $schema     Schema to use for validation.
     * @param   int         $mode       Optional schema validation mode.
     * @param   string      $charset    Optional charset. Defaults to "default_charset" setting in php.ini.
     */
    public function __construct(array $schema, $mode = self::SCHEMA_STRICT | self::VALIDATE_CHARSET, $charset = null)
    {
        $this->schema = (!isset($schema['default']) && isset($schema['validator'])
                         ? ['default' => $schema]
                         : $schema);

        $mode = $mode & 3;

        $this->mode = ($mode & 3 ?: self::SCHEMA_STRICT);
        $this->validate_charset = (bool)(1 & ($mode >> 2));
        $this->fail_early = (bool)(1 & ($mode >> 3));
    }

    /**
     * Get's called when var_dump is used with class instance.
     *
     * @return  array                   Relevant class instance data.
     */
    public function __debugInfo()
    {
        return [
            'schema' => $this->schema,
            'data'   => $this->data
        ];
    }

    /**
     * Add validation error.
     *
     * @param   string      $msg        Error message to add.
     */
    public function addError($msg)
    {
        $this->errors[] = $msg;
    }

    /**
     * Return collected error messages.
     *
     * @return  array                   Error messages.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Return sanitized data.
     *
     * @return  array                   Data.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns whether validation succeeded.
     *
     * @return  bool                    Returns true, if validation succeeded.
     */
    public function isValid()
    {
        return $this->is_valid;
    }

    /**
     * Schema validator.
     *
     * @param   mixed       $data       Value to validate.
     * @param   array       $schema     Expected schema of value.
     * @param   int         $level      Current depth in value.
     * @param   int         $max_depth  Parameter for specifying max. allowed depth of nested sub-elements.
     * @param   array       $ref        Stored references.
     * @return  bool                    Returns true if validation succeeded.
     */
    protected function _validator($data, array $schema, $level = 0, $max_depth = 0, array &$ref = [])
    {
        if (!($return = ($max_depth == 0 || $level <= $max_depth))) {
            // max nested depth is reached
            return $return;
        }

        if (isset($schema['keyrename'])) {
            // rename keys first before continuing
            $map =& $schema['keyrename'];
            $data = array_combine(array_map(function ($v) use ($map) {
                return (isset($map[$v])
                        ? $map[$v]
                        : $v);
            }, array_keys($data)), array_values($data));
        }

        if (isset($schema['ref'])) {
            // add reference to field
            $ref[$schema['ref']] =& $data;
        }

        if (isset($schema['preprocess']) && is_callable($schema['preprocess'])) {
            // there's a data preprocessor configured
            $data = $schema['preprocess']($data);
        }

        if ($schema['validator'] == validate::T_ARRAY) {
            // array validation
            do {
                if (!is_array($data)) {
                    if (!($return = !isset($schema['required']))) {
                        $this->addError($schema['required']);
                    }

                    break;
                }

                $cnt = count($data);

                if (!($return = (isset($schema['max_items']) && $cnt <= $schema['max_items']))) {
                    if (isset($schema['invalid'])) {
                        $this->addError($schema['invalid']);
                    }
                    break;
                }
                if (!($return = (isset($schema['min_items']) && $cnt >= $schema['min_items']))) {
                    if (isset($schema['invalid'])) {
                        $this->addError($schema['invalid']);
                    }
                    break;
                }

                if (is_array($schema['items'])) {
                    $subschema = $schema['items'];
                } elseif (is_scalar($schema['items']) && isset($this->schema[$schema['items']])) {
                    $subschema = $this->schema[$schema['items']];
                } else {
                    // no sub-validation-schema available, continue
                    throw new \Exception("schema error -- no subschema '" . $schema['items'] . "' available");
                    $return = false;
                    break;
                }

                for ($i = 0; $i < $cnt; ++$i) {
                    list($return, $data[$i]) = $this->_validator(
                        $data[$i],
                        $subschema,
                        $level + 1,
                        (isset($schema['max_depth'])
                         ? $level + $schema['max_depth']
                         : $max_depth),
                        $ref
                    );

                    if (!$return && $this->fail_early) {
                        break;
                    }
                }
            } while (false);
        } elseif ($schema['validator'] == validate::T_OBJECT) {
            // object validation
            do {
                if (!is_array($data)) {
                    if (!($return = !isset($schema['required']))) {
                        $this->addError($schema['required']);
                    }

                    break;
                }

                // validate if same properties are available in value and schema
                if (!isset($schema['properties'])) {
                    throw new \Exception("schema error -- no properties available");
                }

                $schema = $schema['properties'];

                $cnt1 = count($schema);
                $cnt2 = count($data);
                $cnt3 = count(array_intersect_key($schema, $data));

                if (!($return = ($cnt1 >= $cnt3 || ($cnt1 < $cnt2 && $this->mode != self::SCHEMA_STRICT)))) {
                    if (isset($schema['invalid'])) {
                        $this->addError($schema['invalid']);
                    }
                    break;
                }

                if ($cnt1 > $cnt3) {
                    // iterate over missing fields and check, if they are required
                    foreach (array_diff_key($schema, $data) as $k => $v) {
                        if (isset($schema[$k]['required'])) {
                            $this->addError($schema[$k]['required']);

                            $return = false;

                            if ($this->fail_early) {
                                break(2);
                            }
                        }
                    }
                }

                // validate each property
                foreach ($data as $k => &$v) {
                    if (!isset($schema[$k])) {
                        // unknown field
                        if ($this->mode == self::SCHEMA_CLEANUP) {
                            unset($data[$k]);
                        }

                        continue;
                    }

                    list($return, $data[$k]) = $this->_validator($data[$k], $schema[$k], $level, $max_depth, $ref);

                    if (!$return && $this->fail_early) {
                        break(2);
                    }
                }
            } while (false);
        } elseif ($schema['validator'] == validate::T_CHAIN) {
            // validation chain
            if (!isset($schema['chain'])) {
                throw new \Exception("schema error -- no chain available");
            }

            foreach ($schema['chain'] as $item) {
                list($return, $data) = $this->_validator($data, $item, $level, $max_depth, $ref);

                if (!$return && $this->fail_early) {
                    break;
                }
            }
        } elseif ($schema['validator'] == validate::T_CALLBACK) {
            // validating using callback
            if (!isset($schema['callback']) || !is_callable($schema['callback'])) {
                throw new \Exception("schema error -- no valid callback available");
            }

            if (!($return = $schema['callback']($data, $ref)) && isset($schema['invalid'])) {
                $this->addError($schema['invalid']);
            }
        } else {
            // validation using validator
            $validator = $schema['validator'];

            if (is_scalar($validator) && class_exists($validator) && is_subclass_of($validator, '\Octris\Validate\Type')) {
                $validator = new $validator(
                    (isset($schema['options']) && is_array($schema['options'])
                        ? $schema['options']
                        : [])
                );
            }

            if (!($validator instanceof \Octris\Validate\Type)) {
                throw new \Exception("'$validator' is not a validation type");
            }

            $data = $validator->preFilter($data);

            if ($data === '' && isset($schema['required'])) {
                $this->addError($schema['required']);
            } else {
                if ($return = (!$this->validate_charset || (\Octris\Validate\Validator\Encoding::getInstance(['charset' => $this->charset]))->validate($data))) {
                    $return = $validator->validate($data);
                } else {
                    $this->addError('Invalid encoding');
                }

                if (!$return && isset($schema['invalid'])) {
                    $this->addError($schema['invalid']);
                }
            }
        }

        if (!$return && isset($schema['onFailure']) && is_callable($schema['onFailure'])) {
            $schema['onFailure']();
        } elseif ($return && isset($schema['onSuccess']) && is_callable($schema['onSuccess'])) {
            $schema['onSuccess']();
        }

        return [$return, $data];
    }

    /**
     * Apply validation schema to a specified array of values.
     *
     * @param   mixed           $data               Data to validate.
     * @return  bool                                Returns true if value is valid compared to the schema configured in the validator instance.
     */
    public function validate($data)
    {
        if (!isset($this->schema['default'])) {
            throw new \Exception('no default schema specified!');
        }

        $this->errors = [];

        list($return, $data) = $this->_validator(
            $data,
            $this->schema['default']
        );

        $this->data     = $data;
        $this->is_valid = $return;

        return ($return !== false ? $data : $return);
    }
}

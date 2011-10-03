<?php

/**
 * xValidation class
 * PHP validation class
 * @author Aliaksandr Astashenkau
 * @author-email dfsq.dfsq@gmail.com
 * @author-website http://dfsq.info
 * @license MIT License - http://www.opensource.org/licenses/mit-license.php
 * @version 1.0
 */

/**
 * Value is required and cannot be empty.
 */
define('CH_REQUIRED', 1);

/**
 * Length validation.
 * Parameters min and/or max can be provided.
 * 'username' => array(
 *     'check' => CH_ALNUM | CH_LENGTH,
 *     'min'   => 4,
 *     'max'   => 8
 * )
 */
define('CH_LENGTH', 2);

/**
 * Value should be an email address.
 */
define('CH_EMAIL', 4);

/**
 * Value should be URL.
 */
define('CH_URL', 8);

/**
 * Value can contain only alpha-numeric characters, i.e., letters and numbers
 * with a letter first. Useful for username validation.
 */
define('CH_ALNUM', 16);

/**
 * Value should be unsigned integer.
 */
define('CH_UNSIGNED', 32);

/**
 * Value should be uploaded file.
 * Can accept several parameters.
 * ext - file extension, e.g., jpg, png, pdf, mp3.
 * type - mime type
 * 'avatar' => array(
 *     'check' => CH_REQUIRED | CH_FILE,
 *     'exttension' => 'jpg,png'
 * )
 */
define('CH_FILE', 64);

/**
 * Use regular expression for validation.
 * 'date' => array(
 *     'check' => CH_REGEXP,
 *     'rule'  => '/(\d{4})-(\d{2})-(\d{2})/'
 * )
 */
define('CH_REGEXP', 128);

/**
 * Perform custom validation. Custom validation is run after all other constraints are done.
 * For PHP 5.3 you can specify custom anonymuos function to be extecuted. This function must
 * return boolean value. This function receives an input parameter $value which is
 * value of the field being validated. You can also use array instead of function. See examples.
 *
 * Check if such username is already present in database table tbl_user.
 *
 * 'username' => array(
 *     'check'  => CH_REQUIRED | CH_ALNUM | CH_CUSTOM,
 *     'custom' => fuction($value, $configArray) use ($dbConnector) {
 *         return !$dbConnector->getOne('username=:username', 'tbl_user', array(
 *             'username' => $value
 *         ));
 *     }
 * )
 *
 * Or more likely you will need to provide some additional information for your
 * custom validation such as custom error message:
 *
 * 'username' => array(
 *     'check'  => CH_REQUIRED | CH_ALNUM | CH_CUSTOM,
 *     'custom' => array(
 *         'message' => 'This username is already taken. Please chose another one.',
 *         'method'  => fuction($value, $configArray) use ($dbConnector, $whatever) { ... }
 *     )
 * )
 *
 * Or the same can be achived with the following approach without anonymous function:
 *
 * 'username' => array(
 *     'check'  => CH_REQUIRED | CH_ALNUM | CH_CUSTOM,
 *     'custom' => array($this, 'isUsernameUnique')
 * )
 *
 * Or
 *
 * 'username' => array(
 *     'check'  => CH_REQUIRED | CH_ALNUM | CH_CUSTOM,
 *     'custom' => array(
 *         'message' => 'This username is already taken. Please chose another one.',
 *         'custom'  => array($this, 'isUsernameUnique')
 *     )
 * )
 *
 * Former example will pass value of 'custom' element into call_user_func_array like this:
 * call_user_func_array(array($this, 'isUsernameUnique'), array($value, $configArray))
 */
define('CH_CUSTOM', 256);

/**
 * For repeated fields like password or email confirmation fields.
 * 'password' => CH_REQUIRED,
 * 'password_confirm' => CH_COMFIRM
 *
 * Or
 *
 * 'password' => CH_REQUIRED,
 * 'repeat_password' => array(
 *     'check' => CH_CONFIRM,
 *     'field' => 'password'
 * )
 */
define('CH_CONFIRM', 512);


/**
 * Class for handling server side validation.
 * @throws Exception
 */
class xValidator
{
	/**
	 * Initial configuration array.
	 */
	private static $config = array();

	/**
	 * Fields to be checked.
	 * @var array
	 */
	private $fields = array();

	/**
	 * Group of validating fields.
	 * @var string
	 */
	private $group;

	/**
	 * Data which is validated.
	 */
	private $data = array();

	/**
	 * Validation fail errors found during checking.
	 * @var array
	 */
	private $errors = array();

	/**
	 * @param array $config
	 * If configuration array consists of only one element, it's considered to be fields array.
	 * Otherwise we look for fields configuration in $config['fields'].
	 */
	function __construct(array $config)
	{
		if (!is_array($config))
		{
			throw new Exception('Validation configuration must be an array.');
		}

		self::$config = $config;

		// fields
		if (count($config) == 1 && !isset($config['fields']))
		{
			$this->fields = $config;
		}
		else
		{
			if (!isset($config['fields']))
			{
				throw new Exception('Validation configuration array must contain element "fields".');
			}

			$this->fields = $config['fields'];
		}

		// fields group
		if (isset($config['group'])) $this->group = $config['group'];
	}

	/**
	 * Perform validation against conditions specified in configuration.
	 * @param $post
	 * @return boolean
	 */
	public function check($post)
	{
		$data = isset($this->group) ? $post[$this->group] : $post;

		// remember data
		$this->data = $data;

		// check if there are absent fields in the incomming data
		// which also need to be validated.
		$diff = array_diff_key($this->fields, $data);

		foreach ($diff as $k => $val)
		{
			$data[$k] = '';
		}

		foreach ($data as $key => $value)
		{
			if (isset($this->fields[$key]))
			{
				Constraint::clearErrors();

				if (!is_array($this->fields[$key]))
				{
					$this->fields[$key] = array('check' => $this->fields[$key]);
				}

				$check = array_merge($this->fields[$key], array(
					'field' => $key,
					'group' => isset($this->group) ? $this->group : null,
					'data'  => $this->data
				));

				if (!static::is($value, $check))
				{
					$this->pushError($key, Constraint::getErrors());
				}
			}
		}

		return !$this->hasErrors();
	}

	/**
	 * Check one field against rules.
	 * Allows to use validator for checking separate values. E.g.:
	 * Validator::is($_GET['username'], CH_ALNUM | CH_REQURED) or
	 * Validator::is($_GET['username'], array('check' => CH_ALNUM | CH_REQURED | CH_LENGTH, 'min' => 4))
	 * @static
	 * @param  $value
	 * @param  $check
	 * @return boolean
	 */
	public static function is($value, $check)
	{
		$rule = is_int($check) ? $check : $check['check'];

		// File validation requires different approach.
		if ($rule & CH_FILE) FileConstraint::check($check);
		else
		{
			if ($rule & CH_REQUIRED) RequiredConstraint::check($value, $check);
			if ($rule & CH_LENGTH)   LengthConstraint::check($value, $check);
			if ($rule & CH_EMAIL)    EmailConstraint::check($value, $check);
			if ($rule & CH_URL)      URLConstraint::check($value, $check);
			if ($rule & CH_ALNUM)    AlnumConstraint::check($value, $check);
			if ($rule & CH_UNSIGNED) UnsignedConstraint::check($value, $check);
			if ($rule & CH_REGEXP)   RegexpConstraint::check($value, $check);
			if ($rule & CH_CUSTOM)   CustomConstraint::check($value, $check);
			if ($rule & CH_CONFIRM)  ConfirmConstraint::check($value, $check);
		}

		return false;
	}

	public function hasErrors()
	{
		foreach ($this->errors as $value)
		{
			if (count($value)) return true;
		}

		return false;
	}

	private function pushError($field, $errors)
	{
		$this->errors[$field] = $errors;
	}

	/**
	 * Return array of errors.
 	 * @return array
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	public function getData($field=null)
	{
		return $field ? $this->data[$field] : $this->data;
	}

	/**
	 * Display particular error for the field.
	 * @param $field string name of the field.
	 * @param $delim string delimeter to use for error concatenation
	 * @return string
	 */
	public function error($field, $delim=null)
	{
		return $delim ? implode($delim, $this->errors[$field]) : $this->errors[$field];
	}

	public function hasError($field)
	{
		return count($this->error($field));
	}
}


/**
 * Parent class for all validator constraints.
 */
abstract class Constraint
{
	/**
	 * @var string
	 */
	private static $lastErrors = array();

	/**
	 * Perform validation for certain type.
	 * @static
	 * @param  $value
	 * @param  $rule configuration array of settings.
	 * @return boolean
	 */
	public static function check($value, $rule)
	{
		// implementation
	}

	public static function clearErrors()
	{
		self::$lastErrors = array();
	}

	protected function pushError($msg)
	{
		self::$lastErrors[] = $msg;
	}

	public static function getErrors()
	{
		return array_unique(self::$lastErrors);
	}
}

/**
 * Reqired validator.
 */
class RequiredConstraint extends Constraint
{
	const ERROR = 'Value can not be blank.';

	public static function check($value, $r)
	{
		// If validating group of checkboxes
		if (is_array($value))
		{
			return true;
		}

		if (!trim($value))
		{
			self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
			return false;
		}

		return true;
	}
}

/**
 * Length validator.
 */
class LengthConstraint extends Constraint
{
	const ERROR_1 = 'Value should contain between %d and %d characters.';
	const ERROR_2 = 'Value should contatain minimum %d characters.';
	const ERROR_3 = 'Value should contatain maximum %d characters.';

	public static function check($value, $r)
	{
		$len = (function_exists('mb_strlen')) ? mb_strlen($value) : strlen($value);

		if (isset($r['min']) && isset($r['max']))
		{
			if ($len < $r['min'] or $len > $r['max'])
			{
				self::pushError(isset($r['message']) ? $r['message'] : sprintf(self::ERROR_1, $r['min'], $r['max']));
				return false;
			}
		}
		elseif (isset($r['min']) and !isset($r['max']))
		{
			if ($len < $r['min'])
			{
				self::pushError(isset($r['message']) ? $r['message'] : sprintf(self::ERROR_2, $r['min']));
				return false;
			}
		}
		elseif (isset($r['max']) and !isset($r['min']))
		{
			if ($len > $r['max'])
			{
				self::pushError(isset($r['message']) ? $r['message'] : sprintf(self::ERROR_3, $r['max']));
				return false;
			}
		}

		return true;
	}
}

/**
 * Email validator.
 */
class EmailConstraint extends Constraint
{
	const EMAIL_PATTERN = '#^(\b[\w\.%\-&]+\b|"[^"]+")@\b[\w\-&]+\b(\.\b[\w\-&]+\b)*\.[A-Za-z]{2,4}$#';
	const ERROR = 'Value is not a valid email address.';

	public static function check($value, $r)
	{
		if (!preg_match(self::EMAIL_PATTERN, $value))
		{
			self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
			return false;
		}

		return true;
	}
}

/**
 * URL validator.
 */
class URLConstraint extends Constraint
{
	const URL_PATTERN = '#^(https?:\/\/|)(\b[\w\-&]+\b(\.\b[\w\-&]+\b)*\.[A-Za-z]{2,4}|\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(:\d+|)(\/[^?]*|)(\?.*|)$#i';
	const ERROR = 'Value is not a valid URL address.';

	public static function check($value, $r)
	{
		if (!preg_match(self::URL_PATTERN, $value))
		{
			self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
			return false;
		}

		return true;
	}
}

/**
 * Alpha-numeric validator.
 */
class AlnumConstraint extends Constraint
{
	const ALNUM_PATTERN = '#^[A-Za-z][A-Za-z0-9\_]+$#';
	const ERROR = 'You can only use letters, numbers and _ with a letter first.';

	public static function check($value, $r)
	{
		if (!preg_match(self::ALNUM_PATTERN, $value))
		{
			self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
			return false;
		}

		return true;
	}
}

/**
 * Unsighned validator.
 */
class UnsignedConstraint extends Constraint
{
	const ERROR   = 'Value must be an unsigned number.';
	const ERROR_1 = 'Value must be between %d and %d.';
	const ERROR_2 = 'Value must be minimum %d.';
	const ERROR_3 = 'Value must be maximum %d.';

	public static function check($value, $r)
	{

		if (!is_numeric($value) || $value < 0)
		{
			self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
			return false;
		}

		if (isset($r['min']) && isset($r['max']))
		{
			if ($value < $r['min'] or $value > $r['max'])
			{
				self::pushError(isset($r['message']) ? $r['message'] : sprintf(self::ERROR_1, $r['min'], $r['max']));
				return false;
			}
		}
		elseif (isset($r['min']) and !isset($r['max']))
		{
			if ($value < $r['min'])
			{
				self::pushError(isset($r['message']) ? $r['message'] : sprintf(self::ERROR_2, $r['min']));
				return false;
			}
		}
		elseif (isset($r['max']) and !isset($r['min']))
		{
			if ($value > $r['max'])
			{
				self::pushError(isset($r['message']) ? $r['message'] : sprintf(self::ERROR_3, $r['max']));
				return false;
			}
		}

		return true;
	}
}

/**
 * File upload constraint.
 */
class FileConstraint extends Constraint
{
	/**
	 * Default message.
	 */
	const ERROR = 'The error occured while uploading file.';

	/**
	 * The uploaded file exceeds the MAX_FILE_SIZE directive
	 * that was specified in the HTML form.
	 */
	const ERROR_2 = 'Filesize excedeed max allowed size.';

	/**
	 * For required fields.
	 */
	const ERROR_4 = 'Please select file.';

	/**
	 * File with incorrect extension.
	 */
	const ERROR_EXTENSION = 'Allowed file types are %s.';

	/**
	 * We deal directly with $_FILE array.
	 * @static
	 * @param  $r array of configuration.
	 * @return void
	 */
	public static function check($r)
	{
		if (!empty($r['group']))
		{
			$file = array();
			foreach ($_FILES[$r['group']] as $key => $value)
			{
				$file[$key] = $value[$r['field']];
			}
		}
		else
		{
			$file = $_FILES[$r['field']];
		}

		// it there were some errors uploading file
		if ($file['error'] > 0)
		{
			switch ($file['error'])
			{
				case 2:
					self::pushError(isset($r['message']) ? $r['message'] : self::ERROR_2);
					break;

				case 4:
					if ($r['check'] & CH_REQUIRED)
					{
						self::pushError(isset($r['message']) ? $r['message'] : self::ERROR_4);
						return false;
					}
					break;

				default:
					self::pushError(self::ERROR);
			}

			return false;
		}

		// check extension first
		if (isset($r['extension']))
		{
			$allowed = array_map(create_function('$arr', 'return trim($arr, " ");'), explode(',', $r['extension']));
			$fileExt = substr(strrchr($file['name'], '.'), 1);

			if (!in_array($fileExt, $allowed))
			{
				self::pushError(sprintf(self::ERROR_EXTENSION, $r['extension']));
				return false;
			}
		}

		return true;
	}
}

/**
 * Regular expression validator.
 */
class RegexpConstraint extends Constraint
{
	const ERROR = 'Validation on this field failed.';

	public static function check($value, $r)
	{
		if (!preg_match($r['rule'], $value))
		{
			self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
			return false;
		}

		return true;
	}
}

/**
 * Custom validator.
 */
class CustomConstraint extends Constraint
{
	const ERROR = 'Validation on this field failed.';

	public static function check($value, $r)
	{
		if (is_array($r['custom']))
		{
			if (isset($r['custom']['method']))
			{
				$error = isset($r['custom']['message'])
						? $r['custom']['message']
						: ( isset($r['message']) ? $r['message'] : self::ERROR )
				;

				if ($r['custom']['method'] instanceof Closure)
				{
					if (!$r['custom']['method']($value, $r))
					{
						self::pushError($error);
						return false;
					}

					return true;
				}
				elseif (is_array($r['custom']['method']))
				{
					$result = call_user_func_array($r['custom']['method'], func_get_args());
					if (!$result)
					{
						self::pushError($error);
						return false;
					}

					return true;
				}
			}
			else
			{
				$result = call_user_func_array($r['custom'], func_get_args());
				if (!$result)
				{
					self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
					return false;
				}

				return true;
			}
		}
		else if ($r['custom'] instanceof Closure)
		{
			if (!$r['custom']($value, $r))
			{
				self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
				return false;
			}

			return true;
		}

		return false;
	}
}

/**
 * Confirmation constraint.
 */
class ConfirmConstraint extends Constraint
{
	const ERROR = 'Confirmation failed.';

	public static function check($value, $r)
	{
		$field = isset($r['confirm']) ? $r['confirm'] : str_replace('_confirm', '', $r['field']);

		if (!isset($r['data'][$field]))
		{
			return false;
		}

		if ($value != $r['data'][$field])
		{
			self::pushError(isset($r['message']) ? $r['message'] : self::ERROR);
			return false;
		}

		return true;
	}
}
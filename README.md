# PHP xValidator class #

Useful, simple and completely independent class for validation form data.

## Usage

Here is an example of of how you can use it to validate your form:

```php
// Include class itself
require_once "xValidator.class.php";

// Create new instance and configure validator
$validator = new xValidator(array(
    'group'  => 'user',
    'fields' => array(
        'fullname' => CH_REQUIRED,
        'email'    => CH_REQUIRED | CH_EMAIL,
        'password' => array(
            'check' => CH_REQUIRED | CH_LENGTH,
            'min'   => 4
        ),
        'password_confirm' => CH_CONFIRM
        )
));
```

And this is how we check form on submit:

```php
// Form submited
if (!empty($_POST))
{
    // Here validator will try to validate information in the array $_POST['user'].
    // If you don't specify option 'group' during initialization
    // validator will take full $_POST array.
    if ($validator->check($_POST))
    {
        // Everithing is OK
        Flash::set('notice', 'Registration successful.');
        redirect('/register/confirmation');
    }

    // Validation failed, display form again
}

// Render form
$this->display('register/index', array(
    'validator' => $validator
));
```


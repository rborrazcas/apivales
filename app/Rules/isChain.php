<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class isChain implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $containsSpecialChars = preg_match(
            '@[' . preg_quote("'=%;-?!¡\"`+") . ']@',
            $value
        );
        return !$containsSpecialChars;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'El campo :attribute no debe contener caracteres especiales.';
    }
}

<?php

namespace Condoedge\Messaging\Rules;

use Illuminate\Contracts\Validation\Rule;

class FilesTotalUploadSize implements Rule
{
    protected $maxSize;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($maxSize)
    {
        $this->maxSize = $maxSize;
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
        $total_size = array_reduce($value, function ( $sum, $item ) {

            $filesize = 0;

            if (is_string($item)) {
                $filesize = json_decode($item)->size;
            } else {
                $filesize = filesize($item->path());
            }

            // each item is UploadedFile Object
            $sum += $filesize;

            return $sum;
        });

        // $parameters[0] in kilobytes
        return $total_size < $this->maxSize * 1024;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('file.total-file-size-cannot-exceed').' '.($this->maxSize/1000).'Mb.';
    }
}

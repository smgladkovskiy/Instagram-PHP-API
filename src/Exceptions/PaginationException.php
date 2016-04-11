<?php namespace MetzWeb\Instagram\Exceptions;

class PaginationException extends InstagramException
{

    /**
     * PaginationException constructor.
     */
    public function __construct($message = null)
    {
        parent::__construct("Pagination error: " . $message);
    }
}
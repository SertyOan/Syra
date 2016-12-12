<?php
namespace Syra;

interface DatabaseInterface {
    public static function getWriter();
    public static function getReader();
}

<?php

namespace Lib\Extend;

use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;

class WebDriverExpectedConditionPlus extends WebDriverExpectedCondition
{
    public static function presenceOfElementNoLocate(WebDriverBy $by)
    {
        return new static(
            function (WebDriver $driver) use ($by) {
                try {
                    return !$driver->findElement($by);
                } catch (NoSuchElementException $e) {
                    return true;
                }
            }
        );
    }

    public static function until($time)
    {
        return new static(
            function (WebDriver $driver) use ($time) {
                return time() >= $time;
            }
        );
    }
}

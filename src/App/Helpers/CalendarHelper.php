<?php

namespace Kolydart\Laravel\App\Helpers;

use DateTime;


class CalendarHelper{

	/**
	 * calculate both dates (Julian, Gregorian) (Ιουλιανό/παλαιό, Γρηγοριανό/νέο)
	 * @requires php-calendar extension
	 * @param  string  $date   ISO8601
	 * @param  boolean $julian true if date is in julian calendar
	 * @return array          ['julian' => $julian_date, 'gregorian' => $gregorian_date]
	 */
	public static function getDates($date, $julian = false):array{

		$arr = [];

		if($julian){
			$arr['julian'] = $date;
			$arr['gregorian'] = self::JDtoISO8601($date);
		}else{
			$arr['julian'] = $date;
			$arr['gregorian'] = self::JDtoISO8601($date);
		}

		return $arr;

	}

	/**
	 * @requires php-calendar extension
	 * @param string $gregorian_date (ISO8601 formated)
	 * @see https://www.php.net/manual/en/function.jdtogregorian.php#88469
	 */
	public static function ISO8601toJD($gregorian_date) {
	    list($day, $month, $year) = array_map('strrev',explode('-', strrev($gregorian_date), 3));
	    if ($year <= 0) $year--;
	    return gregoriantojd($month, $day, $year);
	}

	/**
	 * 
	 * @requires php-calendar extension
	 * @param string $julian_date (ISO8601 formated)
	 * https://www.php.net/manual/en/function.jdtogregorian.php#88469
	 */
	public static function JDtoISO8601($julian_date) {
		$dt = new DateTime($julian_date);
		$int = cal_to_jd(CAL_JULIAN,$dt->format('n'),$dt->format('j'),$dt->format('Y'));
	    if ($int <= 1721425) $int += 365;
	    list($month, $day, $year) = explode('/', jdtogregorian($int));
	    return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}
	

}
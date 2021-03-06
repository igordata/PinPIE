<?php

/**
 * Просто кидает эксепшн.
 * @param String $message
 * @param Integer $number
 * @throws \Exception
 */
function throwE($message='', $number=0) {
  throw new \Exception($message, $number);
}


/**
 * Эта функция кидает эксепшн если вар равен true, соотв кидает его с меседжем и номером, как передали.
 * @param mixed $var Проверяется на нестрогое равенство true и если да - кидается эксепшн.
 * @param string $message Текст сообщения в эксепшене.
 * @param int $number Номер эксепшена.
 * @return bool Всегда true.
 * @throws \Exception
 */
function throwOnTrue($var, $message = '', $number = 0) {
  if ($var) {
    throw new \Exception($message, $number);
  }
  return true;
}

/**
 * Эта функция кидает эксепшн если вар равен фалсу или нулл, соотв кидает его с меседжем и номером, как передали.
 * @param mixed $var Проверяется на нестрогое равенство false и если да - кидается эксепшн.
 * @param string $message Текст сообщения в эксепшене.
 * @param int $number Номер эксепшена.
 * @return bool Всегда true.
 * @throws \Exception
 */
function throwOnFalse($var, $message = '', $number = 0) {
  if (!$var) {
    throw new \Exception($message, $number);
  }
  return true;
}

/**
 * Кидает эксепшн в случае, если переменная не является массивом, или массив пуст.
 * @param mixed $array
 * @param string $message Текст сообщения в эксепшене.
 * @param int $number Номер эксепшена.
 * @return bool Всегда true.
 * @throws \Exception
 */
function throwOnBadArray($array, $message = '', $number = 0) {
  if (!is_array($array) OR empty($array)) {
    throw new \Exception($message, $number);
  }
  return true;
}
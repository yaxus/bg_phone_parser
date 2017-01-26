# Парсер для преобразования CDR в формат [BGBilling]

Для работы парсера требуется php5.3 и выше.

## Загрузка и распаковка архива:
```sh
curl -OL https://github.com/yaxus/bg_phone_parser/archive/master.tar.gz && tar zx -f master.tar.gz
```
or
```sh
wget --no-check-certificate https://github.com/yaxus/bg_phone_parser/archive/master.tar.gz && tar zx -f master.tar.gz
```

## Запуск парсера из командной строки:
```sh
# Перейти в директорию с каталогом парсеров.
cd ./bg_phone_parser-master
# Копировать папку с примером. Здесь будет файл конфига (conf.php) и исполняемый файл (execute.php).
cp -R ./example ./test_parser
# Запуск парсера
php ./test_parser/execute.php
```


## Параметры запуска:
```
php ./test_parser/execute.php -h
use:
<-y YYYY> - Year;
<-m MM>   - Month;
[-d DD]   - Day;
[-c CC]   - Count days;
[-t]      - Today (if isset, other values not used);
[-h]      - This help.
```

## Последовательность работы классов:
```
execute.php
  
  // Parser
  // Сбор CDR в коллектор, архивирование, 
  // отправка комманды в биллинг на загрузку
  Parser::treat($time_day);
  $collector = Collector::init(); // Collector
  $source = new Source($src_cfg, $collector); // Source
  $source->convAllFiles();
    
    // Source
    // Установка параметров Источника (директория, шаблон файлов...)
    // Пропускает записи с нулевой длительностью
    // Пропускает дублирующиеся CDR
    $this->_convFile($f);
    $cdr = $this->cdr_flow; // CDRFlow
    $cdr->setFile($f);
    $cdr->conv($raw_string);
    $this->collector->add($cdr); // Collector
    
      // CDRFlow
      // Преобразование "сырой" CDR в формат для биллинга
      $this->clear_cdr();
      $this->_rawLoad($row);
      $this->base_init();
      return $this->converter->convert($this); // Converter
      
        // Converter
        // Преобразоание в формат E.164
        // Другие функции для преобразования значений CDR
        // $this->conv_func содержит необходимые функции 
        // для конвертирования, которые добавляются по необходимости
        foreach ($this->conv_func as $f)
    
```

[BGBilling]: http://bgbilling.ru/
# Парсер для преобразования CDR в формат [BGBilling]

 Для работы парсера требуется php5.3 и выше.

 Загрузка и распаковка архива:
 ```sh
 wget http://github.com/yaxus/bg_phone_parser/tarball/master | tar xz
 ```

 Запуск парсера из командной строки:
 ```sh
 # php ./parser/execute.php
 ```
 Справка параметров запуска:
 ```sh
 # php ./example/execute.php -h
 use:
 <-y YYYY> - Year;
 <-m MM>   - Month;
 [-d DD]   - Day;
 [-c CC]   - Count days;
 [-t]      - Today (if isset, other values not used);
 [-h]      - This help.
 ```



 [BGBilling]: http://bgbilling.ru/
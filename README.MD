# Парсер для преобразования CDR в формат [BGBilling]

Для работы парсера требуется php5.3 и выше.

Загрузка и распаковка архива:
```sh
curl -OL https://github.com/yaxus/bg_phone_parser/archive/master.tar.gz && tar zx -f master.tar.gz
```
or
```sh
wget --no-check-certificate https://github.com/yaxus/bg_phone_parser/archive/master.tar.gz && tar zx -f master.tar.gz
```

Запуск парсера из командной строки:
```sh
# Перейти в директорию с каталогом парсеров.
cd ./bg_phone_parser-master
# Копировать папку с примером. Здесь будет файл конфига (conf.php) и исполняемый файл (execute.php).
cp -R ./example ./test_parser
# Запуск парсера
php ./test_parser/execute.php
```


Справка параметров запуска:
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



[BGBilling]: http://bgbilling.ru/
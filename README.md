# rfibank-WP-e-commerce

1. Инсталляция:

	• установить в Wordpress плагин WP Commerce и активировать его в настройках;
	
	• скопировать файл rficb.php из архива в директорию /wp-content/plugins/wp-e-commerce/wpsc-merchants/;
	
	• установить соответствующие права на файл, обычно это 664.

2. Настройка:

	• на вкладке «Параметры — Магазин — Платежи» отметить «Rficb» и произвести настройку необходимых параметров в правом блоке;
	
	• после завершения ввода настроек нажать «Обновить»;
	
	• можно добавлять товар, настраивать шаблон WP-Commerce под свои нужды.
	
3. Настройка в личном кабинете Rficb

  • Нужно указать url обработчика ответов от системы, в поле "URL скрипта обработчика на Вашем сайте:" введите #вашсайт/?rficb_callback=true

    
Работа мерчанта реализована на текущих версиях Wordpress 3.2.1 и WP-Commerce 3.8.7.1. Работоспособность на старших версиях Wordpress и WP-Commerce не гарантируется.

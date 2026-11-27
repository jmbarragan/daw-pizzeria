# Órdenes para poner en marcha La Focaccia di Trevi con Docker (docker/iniciar.sh).
#   make start   lo instala y lo arranca todo, y abre el navegador
#   make stop    para la aplicación sin borrar nada
#   make reset   borra la base de datos y vuelve a empezar con los datos de prueba

.PHONY: start stop reset

start:
	@sh docker/iniciar.sh start

stop:
	@sh docker/iniciar.sh stop

reset:
	@sh docker/iniciar.sh reset

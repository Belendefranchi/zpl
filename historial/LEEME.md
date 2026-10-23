Los archivos JSON de esta carpeta contienen los trabajos guardados: fecha UTC, nombre del diseño, columnas, datos y ZPL exacto por registro.
La interfaz muestra las fechas en horario de Argentina. No se eliminan trabajos automáticamente.
Los trabajos se guardan antes de descargar o imprimir. Las reimpresiones no dependen del diseño actual.
envios.log registra las selecciones y los registros enviados por TCP. Un envío exitoso no confirma la impresión física.
El número de registro se conserva en los nombres del ZIP, incluso al descargar una selección.
Cada tanda puede contener varios registros; un registro puede generar varias etiquetas físicas según su ZPL.
La carpeta está protegida por .htaccess y sus datos se excluyen de Git. Incluí esta carpeta en las copias de seguridad.

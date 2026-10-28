Esta carpeta alimenta automáticamente el selector de la página.
Agregá diseños base .zpl con variables entre corchetes: [CODBAR], [TIPO], etc.
Se respetan las mayúsculas y minúsculas de las variables del diseño.
Las variables repetidas generan una sola columna, en orden de aparición.
Formato de nombre: etiqueta_ANCHOxALTO_uso.zpl; las medidas se muestran en mm.
Ejemplo: etiqueta_100x25_minirack.zpl → 100 × 25 mm — Minirack.
CODBAR (también en minúsculas) es obligatorio y no admite duplicados dentro del trabajo.

A-frame (etiqueta_60x46_a-frame.zpl): diseño de 60 × 46 mm con texto y código de barras rotados. Columnas, en orden: CANAL, DESCRIPCION, DIN1, DIN2, DIN3, DIN4. CANAL es obligatorio, no admite duplicados y determina el orden inverso de impresión. Se utiliza en el texto y en el código de barras, pero genera una sola columna. Los demás campos pueden quedar vacíos. El diseño indica una copia por registro (^PQ1). Se incorpora al selector automáticamente, sin registrar el archivo en código PHP o JavaScript.

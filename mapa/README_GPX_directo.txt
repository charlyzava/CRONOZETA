DESAFÍO BELLA VISTA — versión que lee GPX desde carpeta

Subir al servidor respetando esta estructura:

/mapa/
  index.html
  /gpx/
    desafio-bella-vista-3k.gpx
    desafio-bella-vista-9k-extendido.gpx
    desafio-bella-vista-13k.gpx
    desafio-bella-vista-21k.gpx

El index.html NO contiene las coordenadas de los recorridos.
Al abrir la página, el navegador hace fetch() de cada archivo GPX.

Importante:
- El 9K usado es el archivo extendido para compartir largada/llegada.
- Los nombres de los archivos deben coincidir exactamente.
- No hace falta subir los GPX a otra ubicación si están dentro de /mapa/gpx/.

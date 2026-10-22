// react-draggable@4.6.0 (transitiva vía react-grid-layout) lee
// process.env.DRAGGABLE_DEBUG sin guarda; en el navegador `process` no
// existe y lanza "process is not defined" al arrastrar/redimensionar los
// widgets del dashboard. Shim mínimo cargado antes que cualquier módulo.
//
// Fichero aparte y no <script> en línea en index.html: la CSP de los clústeres
// (MAYA_CSP del chart) es script-src 'self' y bloquearía el script en línea.
window.process = window.process || { env: {} };

     Plan: Arrancar proyectos CEEDCV con up.sh

     Context

     El usuario quiere levantar todos los proyectos del directorio Desarrollo CEEDCV/ que tienen up.sh y conocer las URLs de acceso. Los 4 proyectos comparten una infraestructura
     base (infra/) con Traefik, PostgreSQL, Redis, RabbitMQ y Keycloak. Cada up.sh ya llama internamente a ../infra/ensure-running.sh, por lo que la infra se levanta automáticamente
      si no está corriendo.

     Proyectos a levantar

     ┌──────────────────────────┬─────────────────────────────────────────────┬──────────────────────────────────┐
     │         Proyecto         │                 Directorio                  │               Tipo               │
     ├──────────────────────────┼─────────────────────────────────────────────┼──────────────────────────────────┤
     │ maya_authorization       │ Desarrollo CEEDCV/maya_authorization/       │ Auth service (Laravel + React)   │
     ├──────────────────────────┼─────────────────────────────────────────────┼──────────────────────────────────┤
     │ maya-dms                 │ Desarrollo CEEDCV/maya-dms/                 │ DMS Full-stack (Laravel + React) │
     ├──────────────────────────┼─────────────────────────────────────────────┼──────────────────────────────────┤
     │ maya-dashboard           │ Desarrollo CEEDCV/maya-dashboard/           │ Frontend (React/Vite)            │
     ├──────────────────────────┼─────────────────────────────────────────────┼──────────────────────────────────┤
     │ log-management-dashboard │ Desarrollo CEEDCV/log-management-dashboard/ │ Monolith (Laravel + Livewire)    │
     └──────────────────────────┴─────────────────────────────────────────────┴──────────────────────────────────┘

     Orden de arranque

     Paso 1 — maya_authorization (primero, es la base de auth)

     cd ~/development/Desarrollo\ CEEDCV/maya_authorization && bash up.sh
     - Levanta infra compartida (Traefik, Keycloak, PostgreSQL, Redis, RabbitMQ) vía ensure-running.sh
     - Arranca backend (Laravel) en puerto 8000 y frontend (React) en puerto 5173
     - Corre migraciones automáticamente

     Paso 2 — Los otros 3 proyectos (en paralelo, una vez la auth esté lista)

     cd ~/development/Desarrollo\ CEEDCV/maya-dms && bash up.sh
     cd ~/development/Desarrollo\ CEEDCV/maya-dashboard && bash up.sh
     cd ~/development/Desarrollo\ CEEDCV/log-management-dashboard && bash up.sh

     Cada uno llama a ensure-running.sh internamente, pero la infra ya estará levantada.

     URLs de acceso (vía Traefik en localhost)

     Infraestructura compartida

     ┌─────────────────────┬───────────────────────────┐
     │      Servicio       │            URL            │
     ├─────────────────────┼───────────────────────────┤
     │ Traefik dashboard   │ http://localhost:8888     │
     ├─────────────────────┼───────────────────────────┤
     │ Keycloak            │ https://keycloak.maya.test │
     ├─────────────────────┼───────────────────────────┤
     │ RabbitMQ management │ http://localhost:15672    │
     └─────────────────────┴───────────────────────────┘

     Aplicaciones

     ┌──────────────────────────┬──────────────────────────────────────────────────────┬────────────────────────────────────────────────────────────────┐
     │         Proyecto         │                     URL Frontend                     │                        URL API/Backend                         │
     ├──────────────────────────┼──────────────────────────────────────────────────────┼────────────────────────────────────────────────────────────────┤
     │ maya_authorization       │ http://maya.localhost (o http://localhost:5173)      │ http://api.localhost/api/v1 (o http://localhost:8000)          │
     ├──────────────────────────┼──────────────────────────────────────────────────────┼────────────────────────────────────────────────────────────────┤
     │ maya-dms                 │ https://dms.maya.test (o http://localhost:5174)  │ https://dms-api.maya.test/api/v1 (o http://localhost:8001) │
     ├──────────────────────────┼──────────────────────────────────────────────────────┼────────────────────────────────────────────────────────────────┤
     │ maya-dashboard           │ http://dashboard.localhost (o http://localhost:5175) │ —                                                              │
     ├──────────────────────────┼──────────────────────────────────────────────────────┼────────────────────────────────────────────────────────────────┤
     │ log-management-dashboard │ http://logs.localhost (o http://localhost:8002)      │ — (monolito)                                                   │
     └──────────────────────────┴──────────────────────────────────────────────────────┴────────────────────────────────────────────────────────────────┘

     ▎ Los dominios *.localhost requieren que Traefik esté corriendo en el puerto 80.
     ▎ Los puertos directos funcionan siempre.

     Verificación

     1. Tras el arranque, visitar http://localhost:8888 (Traefik dashboard) para ver todos los servicios registrados
     2. Comprobar https://keycloak.maya.test que responde (health del IdP)
     3. Visitar cada URL de aplicación y verificar que carga
     4. Para maya-dms y maya_authorization: verificar que el login/auth funciona via Keycloak

     Archivos críticos

     - /home/ggarrido/development/Desarrollo CEEDCV/infra/ensure-running.sh
     - /home/ggarrido/development/Desarrollo CEEDCV/maya_authorization/up.sh
     - /home/ggarrido/development/Desarrollo CEEDCV/maya-dms/up.sh
     - /home/ggarrido/development/Desarrollo CEEDCV/maya-dashboard/up.sh
     - /home/ggarrido/development/Desarrollo CEEDCV/log-management-dashboard/up.sh
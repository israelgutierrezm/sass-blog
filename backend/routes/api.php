<?php

/*
 * Sin rutas aquí a propósito. La API vive en los módulos (`app/Modules/{Modulo}/Http/Routes`),
 * registrada por ModuleServiceProvider bajo el prefijo versionado `api/v1`. El `/api/user` del
 * skeleton se quitó: devolvía el modelo Eloquent crudo (con el id secuencial); el usuario
 * autenticado se obtiene de `GET /api/v1/me` (AuthController + Resource).
 */

# Autenticación, roles y middlewares: Módulo de Facturación (api-personas)

## 1. Matriz de permisos implementada

| Perfil | Middleware | REST permitido | SOAP `consultarReservas()` |
|---|---|---|---|
| administrador | `IsAdministrator` | login, registrarCargo, consultarEstadoCliente, pagarCargo, verificarPazYSalvo | Todas las operaciones |
| estilista | `CanRegisterCharges` | login, registrarCargo, consultarEstadoCliente | Al ejecutar registrarCargo o consultarEstadoCliente |
| cajero | `CanProcessPayments` | login, pagarCargo, verificarPazYSalvo | No permitido |
| recepcionista | `CanCheckClearance` | login, consultarEstadoCliente, verificarPazYSalvo | Solo al ejecutar consultarEstadoCliente |

## 2. Recorrido de una petición

`Postman → auth:sanctum (401 si no hay token válido) → perfil:<método> → middleware del rol (403 si no cumple) → controlador → (si aplica) SOAP consultarReservas()`

- **`auth:sanctum`** responde «¿quién eres?». Sin token o con token inválido devuelve **401** en JSON.
- **`perfil:<método>`** (`DespacharPorRol`) es un despachador. Lee `$request->user()->role` y ejecuta el middleware del rol. Esto es necesario porque varias rutas las comparten varios perfiles y los middlewares de Laravel se encadenan en AND.
- **Los cuatro middlewares** comparan manualmente `$request->user()->role` con su rol y revisan que el método esté en su lista. Si no cumple, abortan **antes del controlador** con HTTP 403 y este JSON exacto: `{ "error": "Permisos insuficientes para ejecutar este método" }`.
- **`soap.acceso`** (`AccesoSoapReservas`) aplica la columna SOAP de la matriz.
  - Cuando el SOAP se invoca desde REST, la operación de origen la fija el controlador (`ReservasSoapService`).
  - Cuando se llama directo a `POST /api/soap`, se toma del header `X-Operacion-Origen`.
  - El header no da ventaja: cada rol solo puede declarar las operaciones que la matriz ya le permite. El administrador puede llamar sin header.

### Token en SOAP Header (Guía C)

`POST /api/soap` acepta el token de dos formas. El header HTTP `Authorization: Bearer` tiene prioridad si viene. Si no viene, se usa el SOAP Header personalizado, que lleva el mismo token del login:

```xml
<soapenv:Header>
   <sal:AuthHeader>
      <sal:token>TOKEN_DEL_LOGIN</sal:token>
      <sal:origen>consultarEstadoCliente</sal:origen>   <!-- opcional -->
   </sal:AuthHeader>
</soapenv:Header>
```

El middleware `TokenDesdeSoapHeader` (alias `soap.token`) lee el `AuthHeader`. Si el token viene ahí, lo convierte en `Authorization: Bearer` antes de `auth:sanctum`, que lo valida igual que siempre. Orden de la ruta: `soap.token → auth:sanctum (401) → soap.acceso (403)`. El parseo del XML usa `LIBXML_NONET` y no expande entidades externas, así que no se puede leer archivos locales (XXE). El token lo emite Sanctum al hacer login, no se genera a mano.

## 3. Archivos

| Archivo | Propósito |
|---|---|
| `database/migrations/2026_11_25_100000_add_role_to_users_table.php` | Columna `role` en `users` |
| `database/migrations/2026_11_25_100100_create_facturacion_tables.php` | `clientes`, `reservas`, `cargos` |
| `database/seeders/UserSeeder.php`, `FacturacionSeeder.php`, `DatabaseSeeder.php` | 4 usuarios con rol y datos de prueba |
| `app/Http/Middleware/*.php` | 4 middlewares de perfil, `DespacharPorRol`, `AccesoSoapReservas`, `TokenDesdeSoapHeader` |
| `bootstrap/app.php` | Alias de los middlewares (Laravel 11+, sin `Kernel.php`) |
| `routes/api.php` | Rutas protegidas |
| `app/Http/Controllers/AuthController.php`, `FacturacionController.php`, `SoapController.php` | Login/logout/me, operaciones REST y endpoint SOAP |
| `app/Services/ReservasSoapService.php`, `app/Soap/*` | Servicio SOAP (NuSOAP, rpc/encoded) con inyección por constructor |
| `tests/Feature/MatrizPermisosTest.php` | Una prueba por celda de la matriz, más 401 y 403 |
| `postman/Salon_Roles_Middlewares.postman_collection.json` | Colección de Postman |

## 4. Puesta en marcha

```
composer require econea/nusoap
php artisan migrate --seed          # o: php artisan migrate:fresh --seed
php artisan serve                   # o la URL de Laragon, ajustando baseUrl en Postman
php artisan test --filter=MatrizPermisosTest
```

Usuarios de laboratorio (contraseña `ClaveDemo123!`): `administrador@salon.test`, `estilista@salon.test`, `cajero@salon.test`, `recepcionista@salon.test`.

## 5. Taller (Guía B)

- **Reto 1.** Autenticación verifica quién eres (token Bearer válido → 401 si falla). Autorización verifica qué puedes hacer (rol → 403 si falla). El token es la credencial emitida tras validar usuario y contraseña. Bearer es el esquema `Authorization: Bearer TOKEN`. Un middleware es un filtro que examina la petición antes del controlador y puede bloquearla.
- **Reto 4.**
  - El **alias** se cambia en `bootstrap/app.php`.
  - El **criterio de validación** está en cada middleware (`app/Http/Middleware/*`).
  - La **asociación con la ruta** está en `routes/api.php`.
- **Reflexión final.** `auth:sanctum` solo identifica al usuario. Si bastara, un cajero podría registrar cargos y una recepcionista pagarlos. Hace falta autorización por perfil para separar funciones.

## 6. Seguridad

- Contraseñas hasheadas (cast `hashed`).
- El token no lleva `*`; solo el nombre del rol como capacidad informativa.
- Los tokens se revocan con `/api/logout`.
- No guardar tokens en el repositorio: usar variables de Postman.
- Usar HTTPS fuera del entorno local.

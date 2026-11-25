<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una prueba por cada celda de la matriz de permisos (REST y SOAP) más los casos 401 y 403.
 * Ejecutar: php artisan test --filter=MatrizPermisosTest
 */
class MatrizPermisosTest extends TestCase
{
    use RefreshDatabase;

    private const ERROR_403 = ['error' => 'Permisos insuficientes para ejecutar este método'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function token(string $rol): string
    {
        return $this->postJson('/api/login', [
            'email' => "{$rol}@salon.test",
            'password' => 'ClaveDemo123!',
        ])->assertOk()->json('access_token');
    }

    /** @return array<string, array{0:string,1:string,2:bool}> rol, método, permitido */
    public static function matrizRest(): array
    {
        $matriz = [
            'administrador' => ['registrarCargo' => true, 'consultarEstadoCliente' => true, 'pagarCargo' => true, 'verificarPazYSalvo' => true],
            'estilista' => ['registrarCargo' => true, 'consultarEstadoCliente' => true, 'pagarCargo' => false, 'verificarPazYSalvo' => false],
            'cajero' => ['registrarCargo' => false, 'consultarEstadoCliente' => false, 'pagarCargo' => true, 'verificarPazYSalvo' => true],
            'recepcionista' => ['registrarCargo' => false, 'consultarEstadoCliente' => true, 'pagarCargo' => false, 'verificarPazYSalvo' => true],
        ];

        $casos = [];
        foreach ($matriz as $rol => $metodos) {
            foreach ($metodos as $metodo => $permitido) {
                $casos["$rol / $metodo / ".($permitido ? 'permitido' : '403')] = [$rol, $metodo, $permitido];
            }
        }

        return $casos;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('matrizRest')]
    public function test_matriz_rest(string $rol, string $metodo, bool $permitido): void
    {
        $respuesta = $this->withToken($this->token($rol))->{$this->verbo($metodo)}($this->url($metodo), $this->cuerpo($metodo));

        if ($permitido) {
            $this->assertContains($respuesta->status(), [200, 201], "$rol debía poder ejecutar $metodo");
        } else {
            $respuesta->assertStatus(403)->assertExactJson(self::ERROR_403);
        }
    }

    public function test_login_permitido_para_todos_los_perfiles(): void
    {
        foreach (['administrador', 'estilista', 'cajero', 'recepcionista'] as $rol) {
            $this->postJson('/api/login', ['email' => "$rol@salon.test", 'password' => 'ClaveDemo123!'])
                ->assertOk()->assertJsonStructure(['token_type', 'access_token', 'role']);
        }
    }

    public function test_login_con_credenciales_malas_devuelve_401(): void
    {
        $this->postJson('/api/login', ['email' => 'cajero@salon.test', 'password' => 'mala'])
            ->assertStatus(401);
    }

    public function test_sin_token_todas_las_rutas_protegidas_devuelven_401_json(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->postJson('/api/registrarCargo', [])->assertStatus(401);
        $this->getJson('/api/consultarEstadoCliente/1')->assertStatus(401);
        $this->postJson('/api/pagarCargo/1')->assertStatus(401);
        $this->getJson('/api/verificarPazYSalvo/1')->assertStatus(401);
        $this->getJson('/api/soap')->assertStatus(401);
        $this->post('/api/soap', [], ['Content-Type' => 'text/xml', 'Accept' => 'application/json'])->assertStatus(401);
        $this->postJson('/api/personas', [])->assertStatus(401);
    }

    public function test_token_invalido_devuelve_401(): void
    {
        $this->withToken('token-inventado')->getJson('/api/me')->assertStatus(401);
    }

    // ---------- SOAP consultarReservas() ----------

    private function sobreSoap(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sal="SalonBellezaSOAP"><soapenv:Body><sal:consultarReservas soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><clienteId xsi:type="xsd:int">1</clienteId></sal:consultarReservas></soapenv:Body></soapenv:Envelope>';
    }

    private function soap(string $rol, ?string $origen)
    {
        $headers = ['Content-Type' => 'text/xml; charset=utf-8', 'Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->token($rol)];
        if ($origen !== null) {
            $headers['X-Operacion-Origen'] = $origen;
        }

        return $this->call('POST', '/api/soap', [], [], [], $this->transformHeadersToServerVars($headers), $this->sobreSoap());
    }

    /** @return array<string, array{0:string,1:?string,2:bool}> */
    public static function matrizSoap(): array
    {
        return [
            'administrador / sin origen' => ['administrador', null, true],
            'administrador / pagarCargo' => ['administrador', 'pagarCargo', true],
            'estilista / registrarCargo' => ['estilista', 'registrarCargo', true],
            'estilista / consultarEstadoCliente' => ['estilista', 'consultarEstadoCliente', true],
            'estilista / pagarCargo' => ['estilista', 'pagarCargo', false],
            'estilista / sin origen' => ['estilista', null, false],
            'cajero / pagarCargo' => ['cajero', 'pagarCargo', false],
            'cajero / consultarEstadoCliente' => ['cajero', 'consultarEstadoCliente', false],
            'recepcionista / consultarEstadoCliente' => ['recepcionista', 'consultarEstadoCliente', true],
            'recepcionista / registrarCargo' => ['recepcionista', 'registrarCargo', false],
            'recepcionista / verificarPazYSalvo' => ['recepcionista', 'verificarPazYSalvo', false],
            'recepcionista / sin origen' => ['recepcionista', null, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('matrizSoap')]
    public function test_matriz_soap_consultar_reservas(string $rol, ?string $origen, bool $permitido): void
    {
        $respuesta = $this->soap($rol, $origen);

        if ($permitido) {
            $respuesta->assertOk();
            $this->assertStringContainsString('consultarReservasResponse', $respuesta->getContent());
        } else {
            $respuesta->assertStatus(403)->assertExactJson(self::ERROR_403);
        }
    }

    public function test_wsdl_accesible_con_token(): void
    {
        $respuesta = $this->withToken($this->token('administrador'))->get('/api/soap');
        $respuesta->assertOk();
        $this->assertStringContainsString('consultarReservas', $respuesta->getContent());
    }

    // ---------- Token en SOAP Header (Guía C) ----------

    private function soapConSoapHeader(?string $token, ?string $origen = null)
    {
        $auth = '';
        if ($token !== null) {
            $auth = '<sal:AuthHeader><sal:token>'.$token.'</sal:token>'
                .($origen !== null ? '<sal:origen>'.$origen.'</sal:origen>' : '').'</sal:AuthHeader>';
        }

        $sobre = str_replace('<soapenv:Body>', '<soapenv:Header>'.$auth.'</soapenv:Header><soapenv:Body>', $this->sobreSoap());
        $headers = ['Content-Type' => 'text/xml; charset=utf-8', 'Accept' => 'application/json']; // sin Authorization

        return $this->call('POST', '/api/soap', [], [], [], $this->transformHeadersToServerVars($headers), $sobre);
    }

    public function test_soap_acepta_token_en_soap_header_sin_authorization(): void
    {
        $respuesta = $this->soapConSoapHeader($this->token('administrador'));
        $respuesta->assertOk();
        $this->assertStringContainsString('consultarReservasResponse', $respuesta->getContent());
    }

    public function test_soap_header_con_origen_permitido_y_denegado(): void
    {
        $this->soapConSoapHeader($this->token('estilista'), 'registrarCargo')->assertOk();
        $this->soapConSoapHeader($this->token('recepcionista'), 'consultarEstadoCliente')->assertOk();
        $this->soapConSoapHeader($this->token('recepcionista'), 'registrarCargo')->assertStatus(403)->assertExactJson(self::ERROR_403);
        $this->soapConSoapHeader($this->token('cajero'), 'consultarEstadoCliente')->assertStatus(403)->assertExactJson(self::ERROR_403);
    }

    public function test_soap_sin_token_o_con_token_invalido_devuelve_401(): void
    {
        $this->soapConSoapHeader(null)->assertStatus(401);
        $this->soapConSoapHeader('token-inventado')->assertStatus(401);
    }

    // ---------- Reglas de negocio mínimas ----------

    public function test_registrar_cargo_exige_reserva_activa_via_soap(): void
    {
        $token = $this->token('estilista');
        $this->withToken($token)->postJson('/api/registrarCargo', ['cliente_id' => 1, 'descripcion' => 'Extra', 'valor' => 1000])
            ->assertCreated()->assertJsonPath('reservas_activas', 1);
    }

    public function test_pagar_cargo_es_idempotente_y_paz_y_salvo_refleja_el_estado(): void
    {
        $token = $this->token('administrador');
        $this->withToken($token)->getJson('/api/verificarPazYSalvo/1')->assertOk()->assertJsonPath('paz_y_salvo', false);
        $this->withToken($token)->postJson('/api/pagarCargo/1')->assertOk();
        $this->withToken($token)->postJson('/api/pagarCargo/2')->assertOk();
        $this->withToken($token)->postJson('/api/pagarCargo/2')->assertOk();
        $this->withToken($token)->getJson('/api/verificarPazYSalvo/1')->assertOk()->assertJsonPath('paz_y_salvo', true);
    }

    // ---------- helpers ----------

    private function verbo(string $metodo): string
    {
        return in_array($metodo, ['registrarCargo', 'pagarCargo'], true) ? 'postJson' : 'getJson';
    }

    private function url(string $metodo): string
    {
        return $metodo === 'registrarCargo' ? '/api/registrarCargo' : "/api/$metodo/1";
    }

    /** @return array<string, mixed> */
    private function cuerpo(string $metodo): array
    {
        return $metodo === 'registrarCargo'
            ? ['cliente_id' => 1, 'descripcion' => 'Adicional de prueba', 'valor' => 15000]
            : [];
    }
}

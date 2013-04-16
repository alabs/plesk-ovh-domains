<?php

require_once('lib-mbapi/include/modules/registrar/registrar.php');

$className = 'OVH';
$GLOBALS['moduleInfo'][$className] = array(
    'type' => 'registrar',
    'status' => Registrar::STATUS_STABLE,
    'version' => '0.1.0',
    'displayName' => 'OVH',
    'author' => 'aLabs',
    'countryCodes' => array('INT'),
);

class OVH extends Registrar
{

	protected $OVH;
	protected $sesion;
	protected $modo_test;

	private static $moduleCapabilities = array(
		REGISTRAR_REGISTERDOMAIN => 1,
		REGISTRAR_RENEWDOMAIN => 1,
		REGISTAR_TRANSFERDOMAIN => 1
	);

	public function getModuleInfo() {
		return $GLOBALS['moduleInfo']['OVH'];
	}

	/**
	 * Devuelve la configuración de la pasarela, un array de dos dimensiones con los siguientes parámetros:
	 *
	 * 1º)NULL, 2º)NULL, 3º)<nombre-interno>, 4º)<valor por defecto>,
	 * 5º)<nombre grupo>: para mostrar la opción dentro un fieldset,
	 * 6º)<etiqueta>: El texto mostrado junto al input, puede ser un identificador de la traducción (*)
	 * 7º)<descripción>, 8º)<tipo>: string que identifica el tipo de input del formulario:
	 *  - "t" → input text
	 *  - "a" → textarea
	 *  - "p" → password
	 *  - "b" → casilla de verificación
	 *  - "r" → casilla de opción
	 *  - "s" → select (desplegable)
	 *  - "h" → campo oculto
	 * 9º)1 si el campo es requerido, 0 si es opcional,
	 * 10º) Número de serie (¿?), 11º) 0,
	 * 12º) array con las opciones para los tipos r y s, NULL para otros tipos. Parámetros:
	 *  -12.1º)NULL, 12.2º)NULL, 12.3º)Valor, 12.4º)Rótulo, 12.5º)Entero con el orden, desde 0
	 *
	 * (*) TRADUCCIÓN:
	 *     Puedes definir tus propios identificadores de traducción en un array asociativo en:
	 *     /opt/plesk-billing/lib/lib-mbapi/include/modules/registrar/translations/OVH/es.php
	 *     o usar los predefinidos que se encuentran en:
	 *     /opt/plesk-billing/lib/lib-billing/include/translations/es.php
	 */	
	public function addDefaultConfigParams() {
		return array(
			array(NULL, NULL, 'USUARIO_OVH', '', '', 'Identificador del usuario de OVH',
				'El identificador de la cuenta de fidelización de OVH', 't', 1, 1, 0, NULL),
			array(NULL, NULL, 'PASSWD_OVH', '', '', 'Contraseña',
				'La contraseña de la cuenta de fidelización de OVH', 'p', 1, 2, 0, NULL),
			/*
			 * Párametros de configuración por defecto para los nuevos dominios
			 */
			array(NULL, NULL, 'DOM_HOSTING', 'none', '', 'Tipo de hosting de OVH',
				'Valores admitidos: none|start1m|perso|pro|business|premium', 's', 1, 3, 0,  
				array(array(NULL, NULL, 'none', 'none', 0),
				array(NULL, NULL, 'start1m', 'start1m', 1),
				array(NULL, NULL, 'perso', 'perso', 2),
				array(NULL, NULL, 'pro', 'pro', 3),
				array(NULL, NULL, 'business', 'business', 4),
				array(NULL, NULL, 'premium', 'premium', 5))),
			array(NULL, NULL, 'DOM_OFFER', 'gold', '', 'Tipo de oferta de dominio de OVH',
				'Valores admitidos: gold|platinum|diamond', 's', 1, 4, 0, 
				array(array(NULL, NULL, 'gold', 'gold', 0),
				array(NULL, NULL, 'platinum', 'platinum', 1),
				array(NULL, NULL, 'diamond', 'diamond', 2))),
			array(NULL, NULL, 'DOM_PROFILE', 'whiteLabel', '', 'Perfil de revendedor',
				'Valores admitidos: none|whiteLabel|agent', 's', 1, 5, 0,
				array(array(NULL, NULL, 'none', 'none', 0),
				array(NULL, NULL, 'whiteLabel', 'whiteLabel', 1),
				array(NULL, NULL, 'agent', 'agent', 2))),
			array(NULL, NULL, 'DOM_OWO', 'no', '', 'Activar servicio de ocultación OWO',
				'Valores admitidos: yes|no', 's', 1, 6, 0,
				array(array(NULL, NULL, 'yes', 'yes', 0),
				array(NULL, NULL, 'no', 'no', 1))),
			array(NULL, NULL, 'DOM_DNS1', 'saturno.alabs.es', '', 'Servidor DNS primario',
				'Servidor DNS primario', 't', 1, 7, 0, NULL),
			array(NULL, NULL, 'DOM_DNS2', 'cosmos.alabs.es', '', 'Servidor DNS secundario',
				'Servidor DNS secundario', 't', 1, 8, 0, NULL),
			array(NULL, NULL, 'DOM_MODO_TEST', TRUE, '', 'Modo pruebas',
				'Con el modo de pruebas activo no se realizarán cargos en tu cuenta de fidelización de OVH', 'b', 1, 9, 0, NULL)
		);
	}
 
	private function registrarError($dominio, $error) {
		$this->addError(TRANS_MBAPIERROR, $error);
		$this->commandStatus = self::ACTION_STATUS_ERROR;
		$this->arrayData['DomainName'] = $dominio;
		$this->arrayData['DomainRegistrar'] = 'OVH';
		$this->arrayData['Success'] = false;
		$this->hasExecutedSuccessfully = 0;
		$this->hasExecuted =  1;
		$this->xmlData = $this->getRegistrarReturnXML();
		return $this->xmlData;
	}

	private function todoBien($dominio) {
		$this->arrayData['DomainName'] = $dominio;
		$this->arrayData['DomainRegistrar'] = 'OVH';
		$this->arrayData['Success'] = true;
		$this->hasExecutedSuccessfully = 1;
		$this->hasExecuted =  1;
		$this->xmlData = $this->getRegistrarReturnXML();
		return $this->xmlData;
	}

    public function registerDomain() {
		try {
			$dominio = $this->input['domainSLD'].'.'.$this->input['domainTLD'];
			$usuario = $this->config['USUARIO_OVH'];
			// Comprobamos si el dominio está disponible para su registro o ya ha sido registrado
			$disponible = $this->OVH->domainCheck($this->sesion, $dominio);
			$disponible = $disponible[0]->value;
			if (!$disponible) {
				return $this->registraError ($dominio, 'Llegas tarde: '.$dominio.' ya estaba registrado');
			} else {
				// El dominio está libre. Procedemos al registro
				$OVH->resellerDomainCreate(
					$sesion, 
					$dominio, 
					$this->config['DOM_HOSTING'], 
					$this->config['DOM_OFFER'], 
					$this->config['DOM_PROFILE'], 
					$this->config['DOM_OWO'], 
					$usuario,  //owner
					$usuario,  //admin
					$usuario,  //tech
					$usuario,  //billing 
					$this->config['DOM_DNS1'], 
					$this->config['DOM_DNS2'], 
					'', '', '',//DNS3, DNS4, DNS5
					'',        //method
					'','','','','','','', //$legalName, $legalNumber, $afnicIdent, $birthDate, $birthCity, $birthDepartement, $birthCountry, 
					$this->modo_test);

				// resellerDomainCreate no devuelve nada. Así que sólo podemos asumir que ha sido correcta
				$this->commandStatus = self::ACTION_STATUS_COMPLETED;			
			}
			return todoBien($dominio);
		} catch (SoapFault $error) {
			return $this->registrarError($dominio, 'Error en la conexión con SOAP');
		}
    }

	public function renewDomain() {
		try {	
			$dominio = $this->input['domainSLD'].'.'.$this->input['domainTLD'];
			$OVH->resellerDomainRenew($this->sesion, $dominio, $this->modo_test);
			return $this->todoBien($dominio);
		} catch (SoapFault $error) {
			return $this->registrarError($dominio, 'Error en la conexión con SOAP');
		}
	}

	public function transferDomain() {
		try {
			$dominio = $this->input['domainSLD'].'.'.$this->input['domainTLD'];
			$usuario = $this->config['USUARIO_OVH'];

			$OVH->resellerDomainTransfer(
				$sesion, 
				$dominio, 
				$this->config['DOM_HOSTING'], 
				$this->config['DOM_OFFER'], 
				$this->config['DOM_PROFILE'], 
				$this->config['DOM_OWO'], 
				$usuario,  //owner
				$usuario,  //admin
				$usuario,  //tech
				$usuario,  //billing 
				$this->config['DOM_DNS1'], 
				$this->config['DOM_DNS2'], 
				'', '', '',//DNS3, DNS4, DNS5
				'',        //method
				'','','','','','','', //$legalName, $legalNumber, $afnicIdent, $birthDate, $birthCity, $birthDepartement, $birthCountry, 
				$this->modo_test);

			// resellerDomainCreate no devuelve nada. Así que sólo podemos asumir que ha sido correcta
			$this->commandStatus = self::ACTION_STATUS_COMPLETED;			

			return todoBien($dominio);
		} catch (SoapFault $error) {
			return $this->registrarError($dominio, 'Error en la conexión con SOAP');
		}
	}

	function __construct()  {
		parent::__construct();
		try {
			$this->OVH = new SoapClient('https://www.ovh.com/soapi/soapi-re-1.58.wsdl');
			$this->sesion = $this->OVH->login($this->config['USUARIO_OVH'], $this->config['PASSWD_OVH'], 'es', false);
			$this->modo_test=FALSE;
			if ($this->config['DOM_MODO_TEST']) $this->modo_test=TRUE;
		} catch (SoapFault $error) {
			return $this->registrarError('', 'Error en la conexión con SOAP');
		}
	}

	function __destruct() {
		parent::__destruct();
		$this->OVH->logout($this->sesion);
	}

}

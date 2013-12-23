<?php

require_once __DIR__ . '\..\entities/Arduino.php';
require_once __DIR__ . '\..\entities/DomotiqueException.php';

/**
 * Description of Recorder : 
 * Enregistre les cartes Arduinos cherchant à se connecter
 */
class Recorder {

    private $arduinos;
    private $socket = null; //La socket "maître" sur laquelle le serveur écoute
    private $client = null; //Contiendra l'id de chaque nouvelle connexion

    public function __construct() {
        echo "Constructeur RECORDER\n";
    }

    public function init($address, $port) {
        echo "init\n";
        if (!$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new DomotiqueException("Impossible de créer socket: [$errorcode] $errormsg \n");
        }
        //on lie la ressource sur laquelle le serveur va écouter
        if (!socket_bind($this->socket, $address, $port)) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new DomotiqueException("Impossible de binder la socket: [$errorcode] $errormsg \n");
        }
        //On prépare l'écoute
        if (!socket_listen($this->socket)) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new DomotiqueException("Impossible de listen la socket: [$errorcode] $errormsg \n");
        }
    }

    public function run() {
        while (true) {
            echo "Attente client arduino ou DAO\n";
            //Le code se bloque jusqu'à ce qu'une nouvelle connexion cliente soit établie
            if (!$this->client = socket_accept($this->socket)) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                throw new DomotiqueException("Erreur avec socket_accept: [$errorcode] $errormsg \n");
            }
            echo "Il y a un client\n";
            // PHP_NORMAL_READ => arduino / PHP_BINARY_READ => pc
            if (!$buf = socket_read($this->client, 2048, PHP_NORMAL_READ)) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                throw new DomotiqueException("Could not read: [$errorcode] $errormsg \n");
            }
            echo "\nLa chaine recu est : " . $buf . "\n";

            // Enregistrer une arduino ou demande de la couche DAO ?            
            $parsedBuf = json_decode($buf);
            if (isset($parsedBuf->{'from'}) && $parsedBuf->{'from'} == "dao") {
                $this->sendArduinos();
            } else {
                $this->recordArduino($parsedBuf);
            }
        }
    }

    private function sendArduinos() {
        echo "Envoi de la liste des arduinos à la couche DAO\n";
        foreach ($this->arduinos as $a) {
            $arduinoArray[] = $a->toArray();
        }
        $json = json_encode($arduinoArray);
        $json .= "\n";
        
        if (!socket_write($this->client, $json, 2048)) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new DomainException("Could not write: [$errorcode] $errormsg \n");
        }
        echo "Message write successfully to DAO\n";
        //socket_close($this->socket);
        
    }

    private function recordArduino($parsedRecordDemand) {
        echo "recordArduino\n";
        $arduino = new Arduino($parsedRecordDemand->{'id'}, $parsedRecordDemand->{'desc'}, $parsedRecordDemand->{'mac'}, $parsedRecordDemand->{'id'}, $parsedRecordDemand->{'port'});
        // L'id des arduinos doit être unique deux arduinos ayant le même id s'écrase
        $this->arduinos[$arduino->getId()] = $arduino;
        //$this->arduinos[] = $arduino;
        echo "Liste des arduinos : ";
        var_dump($this->arduinos);
        //socket_close($this->socket);
    }

}

?>

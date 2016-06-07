<?php

namespace LearnositySdk\Request;

use LearnositySdk\Utils\Json;
use LearnositySdk\Exceptions\ValidationException;

/**
 *--------------------------------------------------------------------------
 * Learnosity SDK - Init
 *--------------------------------------------------------------------------
 *
 * Used to generate the necessary security and request data (in the
 * correct format) to integrate with any of the Learnosity API services.
 *
 */

class Init
{
    /**
     * Which Learnosity service to generate a request packet for.
     * Valid values (see also `$validServices`):
     *  - assess
     *  - author
     *  - data
     *  - events
     *  - items
     *  - questions
     *  - reports
     * @var string
     */
    private $service;

    /**
     * The consumer secret as provided by Learnosity. This is your private key
     * known only by the client (you) and Learnosity, which must not be exposed
     * either by sending it to the browser or across the network.
     * It should never be distributed publicly.
     * @var string
     */
    private $secret;

    /**
     * An associative array of security details. This typically contains:
     *  - consumer_key
     *  - domain (optional depending on which service is being intialised)
     *  - timestamp (optional)
     *  - user_id (optional depending on which service is being intialised)
     *
     * It's important that the consumer secret is NOT a part of this array.
     * @var array
     */
    private $securityPacket;

    /**
     * An optional associative array of request parameters used as part
     * of the service (API) initialisation.
     * @var array
     */
    private $requestPacket;

    /**
     * If `requestPacket` is used, `requestString` will be the string
     * (JSON) representation of that. It's used to create the signature
     * and returned as part of the service initialisation data.
     * @var string
     */
    private $requestString;

    /**
     * An optional value used to define what type of request is being
     * made. This is only required for certain requests made to the
     * Data API (http://docs.learnosity.com/dataapi/)
     * @var string
     */
    private $action;

    /**
     * Most services add the request packet (if passed) to the signature
     * for security reasons. This flag can override that behaviour for
     * services that don't require this.
     * @var boolean
     */
    private $signRequestData = true;

    /**
     * Keynames that are valid in the securityPacket, they are also in
     * the correct order for signature generation.
     * @var array
     */
    private $validSecurityKeys = array('consumer_key', 'domain', 'timestamp', 'expires', 'user_id');

    /**
     * Service names that are valid for `$service`
     * @var array
     */
    private $validServices = array('assess', 'author', 'data', 'events', 'items', 'questions', 'reports');

    /**
     * The algorithm used in the hashing function to create the signature
     */
    private $algorithm = 'sha256';

    /**
     * Instantiate this class with all security and request data. It
     * will be used to create a signature.
     *
     * @param string   $service
     * @param mixed    $securityPacket
     * @param string   $secret
     * @param mixed    $requestPacket
     * @param string   $action
     */
    public function __construct($service, $securityPacket, $secret, $requestPacket = null, $action = null)
    {
        // First validate the arguments passed
        $this->validate($service, $securityPacket, $secret, $requestPacket, $action);

        // Set instance variables based off the arguments passed
        $this->service        = $service;
        $this->securityPacket = $securityPacket;
        $this->secret         = $secret;
        $this->requestPacket  = $requestPacket;
        $this->requestString  = $this->generateRequestString();
        $this->action         = $action;

        // Set any service specific options
        $this->setServiceOptions();

        // Generate the signature based on the arguments provided
        $this->securityPacket['signature'] = $this->generateSignature();
    }

    /**
     * Generate the data necessary to make a request to one of the
     * Learnosity products/services.
     *
     * @param boolean $encode Encode the result as a JSON string
     *
     * @return mixed The data to pass to a Learnosity API
     */
    public function generate($encode = true)
    {
        $output = array();

        switch ($this->service) {
            case 'assess':
            case 'author':
            case 'data':
            case 'items':
            case 'reports':
                // Add the security packet (with signature) to the output
                $output['security'] = $this->securityPacket;

                // Stringify the request packet if necessary
                if (!empty($this->requestPacket)) {
                    $output['request'] = $this->requestPacket;
                }

                // Add the action if necessary (Data API)
                if (!empty($this->action)) {
                    $output['action'] = $this->action;
                }

                if ($this->service === 'data') {
                    $r['security'] = Json::encode($output['security']);
                    if (array_key_exists('request', $output)) {
                        $r['request'] = Json::encode($output['request']);
                    }
                    if (array_key_exists('action', $output)) {
                        $r['action'] = $output['action'];
                    }
                    return $r;
                } elseif ($this->service === 'assess') {
                    $output = $output['request'];
                }
                break;
            case 'questions':
                // Add the security packet (with signature) to the root of output
                $output = $this->securityPacket;

                // Remove the `domain` key from the security packet
                unset($output['domain']);

                // Stringify the request packet if necessary
                if (!empty($this->requestPacket)) {
                    $output = array_merge($output, $this->requestPacket);
                }
                break;
            case 'events':
                // Add the security packet (with signature) to the output
                $output['security'] = $this->securityPacket;
                $output['config'] = $this->requestPacket;
                break;
            default:
                // no default
                break;
        }

        return $encode ? Json::encode($output) : $output;
    }

    /**
     * Generate a JSON string from the requestPacket (array) or null
     * if no requestPacket is required for this request
     *
     * @return mixed
     */
    private function generateRequestString()
    {
        if (empty($this->requestPacket)) {
            return null;
        }
        $requestString = Json::encode($this->requestPacket);
        if (false === $requestString) {
            throw new ValidationException('Invalid data, please check your request packet - ' . Json::checkError());
        }
        return $requestString;
    }

    /**
     * Generate a signature hash for the request, this includes:
     *  - the security credentials
     *  - the `request` packet (a JSON string) if passed
     *  - the `action` value if passed
     *
     * @return string A signature hash for the request authentication
     */
    public function generateSignature()
    {
        $signatureArray = array();

        // Create a pre-hash string based on the security credentials
        // The order is important
        foreach ($this->validSecurityKeys as $key) {
            if (array_key_exists($key, $this->securityPacket)) {
                array_push($signatureArray, $this->securityPacket[$key]);
            }
        }

        // Add the secret
        array_push($signatureArray, $this->secret);

        // Add the requestPacket if necessary
        if ($this->signRequestData && !empty($this->requestString)) {
            array_push($signatureArray, $this->requestString);
        }

        // Add the action if necessary
        if (!empty($this->action)) {
            array_push($signatureArray, $this->action);
        }

        return $this->hashValue($signatureArray);
    }

    /**
     * Hash an array value
     *
     * @param  array  $value An array to hash
     *
     * @return string        The hashed string
     */
    private function hashValue($value)
    {
        return hash($this->algorithm, implode('_', $value));
    }

    /**
     * Set any options for services that aren't generic
     */
    private function setServiceOptions()
    {
        switch ($this->service) {
            case 'assess':
            case 'questions':
                $this->signRequestData = false;
                // The Assess API holds data for the Questions API that includes
                // security information and a signature. Retrieve the security
                // information from $this and generate a signature for the
                // Questions API
                if (
                    $this->service === 'assess' &&
                    array_key_exists('questionsApiActivity', $this->requestPacket)
                ) {
                    // prepare signature parts
                    $signatureParts = array();
                    $signatureParts['consumer_key'] = $this->securityPacket['consumer_key'];
                    if (isset($this->securityPacket['domain'])) {
                        $signatureParts['domain'] = $this->securityPacket['domain'];
                    } elseif (isset($this->requestPacket['questionsApiActivity']['domain'])) {
                        $signatureParts['domain'] = $this->requestPacket['questionsApiActivity']['domain'];
                    } else {
                        $signatureParts['domain'] = 'assess.learnosity.com';
                    }
                    $signatureParts['timestamp'] = $this->securityPacket['timestamp'];
                    if (isset($this->securityPacket['expires'])) {
                        $signatureParts['expires'] = $this->securityPacket['expires'];
                    }
                    $signatureParts['user_id'] = $this->securityPacket['user_id'];
                    $signatureParts['secret'] = $this->secret;

                    // override security parameters in questionsApiActivity
                    $questionsApi = $this->requestPacket['questionsApiActivity'];
                    $questionsApi['consumer_key'] = $signatureParts['consumer_key'];
                    unset($questionsApi['domain']);
                    $questionsApi['timestamp'] = $signatureParts['timestamp'];
                    if (isset($signatureParts['expires'])) {
                        $questionsApi['expires'] = $signatureParts['expires'];
                    } else {
                        unset($questionsApi['expires']);
                    }
                    $questionsApi['user_id'] = $signatureParts['user_id'];
                    $questionsApi['signature'] = $this->hashValue($signatureParts);

                    $this->requestPacket['questionsApiActivity'] = $questionsApi;
                }
                break;
            case 'items':
            case 'reports':
                // The Events API requires a user_id, so we make sure it's a part
                // of the security packet as we share the signature in some cases
                if (
                    !array_key_exists('user_id', $this->securityPacket) &&
                    array_key_exists('user_id', $this->requestPacket)
                ) {
                    $this->securityPacket['user_id'] = $this->requestPacket['user_id'];
                }
                break;
            case 'events':
                $this->signRequestData = false;
                $hashedUsers = array();
                foreach ($this->requestPacket['users'] as $user) {
                    $hashedUsers[$user] = hash(
                        $this->algorithm,
                        $user . $this->secret
                    );
                }
                if (count($hashedUsers)) {
                    $this->requestPacket['users'] = $hashedUsers;
                }
                break;
            default:
                // do nothing
                break;
        }
    }

    /**
     * Validate the arguments passed to the constructor
     *
     * @param  string   $service
     * @param  array    $securityPacket
     * @param  string   $secret
     * @param  array    $requestPacket
     * @param  string   $action
     */
    public function validate($service, &$securityPacket, $secret, &$requestPacket, $action)
    {
        if (empty($service)) {
            throw new ValidationException('The `service` argument wasn\'t found or was empty');
        } elseif (!in_array(strtolower($service), $this->validServices)) {
            throw new ValidationException("The service provided ($service) is not valid");
        }

        // In case the user gave us a JSON securityPacket, convert to an array
        if (!is_array($securityPacket) && is_string($securityPacket)) {
            $securityPacket = json_decode($securityPacket, true);
        }

        if (empty($securityPacket) || !is_array($securityPacket)) {
            throw new ValidationException('The security packet must be an array');
        } else {
            foreach (array_keys($securityPacket) as $key) {
                if (!in_array($key, $this->validSecurityKeys)) {
                    throw new ValidationException('Invalid key found in the security packet: ' . $key);
                }
            }
            if (!array_key_exists('timestamp', $securityPacket)) {
                $securityPacket['timestamp'] = gmdate('Ymd-Hi');
            }

            if ($service === "questions" && !array_key_exists('user_id', $securityPacket)) {
                throw new ValidationException('If using the question api, a user id needs to be specified');
            }
        }

        if (empty($secret) || !is_string($secret)) {
            throw new ValidationException('The `secret` argument must be a valid string');
        }

        // In case the user gave us a JSON requestPacket, convert to an array
        if (!is_array($requestPacket) && is_string($requestPacket)) {
            $requestPacket = json_decode($requestPacket, true);
        }

        if (!empty($requestPacket) && !is_array($requestPacket)) {
            throw new ValidationException('The request packet must be an array');
        }

        if (!empty($action) && !is_string($action)) {
            throw new ValidationException('The action parameter must be a string');
        }
    }
}

<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Wodzy\DisposableEmail\BlocklistInterface;
use Wodzy\DisposableEmail\Exception\InvalidEmailException;

/**
 * Mirrors the README example: the blocklist is injected into the action by the container.
 */
class SignupsController extends Controller
{
    /**
     * @param \Wodzy\DisposableEmail\BlocklistInterface $blocklist Injected by the plugin's container definition.
     * @return \Cake\Http\Response
     */
    public function check(BlocklistInterface $blocklist): Response
    {
        $email = $this->request->getQuery('email');
        try {
            $result = is_string($email) && $blocklist->blocks($email) ? 'blocked' : 'allowed';
        } catch (InvalidEmailException) {
            $result = 'invalid';
        }

        return $this->response->withType('text')->withStringBody($result);
    }
}

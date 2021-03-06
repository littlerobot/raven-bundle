<?php

/*
 * This file is part of the MisdRavenBundle for Symfony2.
 *
 * (c) University of Cambridge
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Misd\RavenBundle\Tests\Functional\src\RavenBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * MainController.
 *
 * @author Chris Wilkinson <chris.wilkinson@admin.cam.ac.uk>
 */
class MainController extends Controller
{
    /**
     * Authenticate action.
     *
     * This replicates logging in to Raven. It has additional parameters:
     *
     * - 'status' defines the expected result
     * - 'problem' defines the expected problem
     * - 'expired' defines whether the response has already expired
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function authenticateAction(Request $request)
    {
        $query = $request->query;

        $redirect = $this->createRedirect(
            $query->get('ver'),
            $query->get('url'),
            $query->get('status', 200),
            $query->get('problem'),
            'true' === $query->get('expired')
        );

        return new RedirectResponse($redirect);
    }

    protected function createRedirect($ver, $url, $status = 200, $problem = null, $expired = false)
    {
        if (false === in_array($status, array(200, 410, 510, 520, 530, 540, 560, 570, 999))) {
            $status = 200;
        }

        $response = array();
        $response['ver'] = $ver;
        $response['status'] = $status;
        $response['msg'] = '';
        $response['issue'] = date('Ymd\THis\Z', $expired ? time() - 36001 : time());
        $response['id'] = '1351247047-25829-18';

        if ('url' === $problem) {
            $response['url'] = 'http://www.example.com/';
        } else {
            $response['url'] = $url;
        }

        $response['url'] = str_replace(array('%', '!'), array('%25', '%21'), $response['url']);

        $response['principal'] = 'test0001';

        switch ($problem) {
            case 'auth':
                $response['auth'] = 'test';
                $response['sso'] = '';
                break;
            case 'sso':
                $response['auth'] = '';
                $response['sso'] = 'test';
                break;
            default:
                $response['auth'] = 'pwd';
                $response['sso'] = '';
                break;
        }

        $response['life'] = 36000;
        $response['params'] = '';

        if ('kid' === $problem) {
            $response['kid'] = 999;
        } else {
            $response['kid'] = 901;
        }

        $data = implode(
            '!',
            array(
                $response['ver'],
                $response['status'],
                $response['msg'],
                $response['issue'],
                $response['id'],
                $response['url'],
                $response['principal'],
                $response['auth'],
                $response['sso'],
                $response['life'],
                $response['params'],
            )
        );
        $pkeyid = openssl_pkey_get_private(
            '-----BEGIN RSA PRIVATE KEY-----
MIICWwIBAAKBgQC4RYvbSGb42EEEXzsz93Mubo0fdWZ7UJ0HoZXQch5XIR0Zl8AN
aLf3tVpRz4CI2JBUVpUjXEgzOa+wZBbuvczOuiB3BfNDSKKQaftxWKouboJRA5ac
xa3fr2JZc8O5Qc1J6Qq8E8cjuSQWlpxTGa0JEnbKV7/PVUFDuFeEI11e/wIDAQAB
AoGACr2jBUkXF3IjeAnE/aZyxEYVW7wQGSf9vzAf92Jvekyn0ZIS07VC4+FiPlqF
93QIFaJmVwVOAA5guztaStgtU9YX37wRPkFwrtKgjZcqV8ReQeC67bjo5v3Odht9
750F7mKWXctZrm0MD1PoDlkLvVZ2hDolHm5tpfP52jPvQ6ECQQDgtI4K3IuEVOIg
75xUG3Z86DMmwPmme7vsFgf2goWV+p4471Ang9oN7l+l+Jj2VISdz7GE7ZQwW6a1
IQev3+h7AkEA0e9oC+lCcYsMsI9vtXvB8s6Bpl0c1U19HUUWHdJIpluwvxF6SIL3
ug4EJPP+sDT5LvdV5cNy7nmO9uUd+Se2TQJAdxI2UrsbkzwHt7xA8rC60OWadWa8
4+OdaTUjcxUnBJqRTUpDBy1vVwKB3MknBSE0RQvR3canSBjI9iJSmHfmEQJAKJlF
49fOU6ryX0q97bjrPwuUoxmqs81yfrCXoFjEV/evbKPypAc/5SlEv+i3vlfgQKbw
Y6iyl0/GyBRzAXYemQJAVeChw15Lj2/uE7HIDtkqd8POzXjumOxKPfESSHKxRGnP
3EruVQ6+SY9CDA1xGfgDSkoFiGhxeo1lGRkWmz09Yw==
-----END RSA PRIVATE KEY-----'
        );

        openssl_sign($data, $signature, $pkeyid);

        openssl_free_key($pkeyid);

        $signature =
            preg_replace(
                array(
                    '#\+#',
                    '#/#',
                    '#=#',
                ),
                array(
                    '-',
                    '.',
                    '_',
                ),
                base64_encode($signature)
            );

        $response['sig'] = $signature;

        switch ($problem) {
            case 'invalid':
                // need an invalid response, so just need to change a value
                $response['id'] = 12312424;
                break;
            case 'incomplete':
                unset($response['id']);
                break;
        }

        return $url.(false !== strpos($url, '?') ? '&' : '?').'WLS-Response='.urlencode(implode('!', $response));
    }
}

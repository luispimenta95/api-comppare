<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Domínios permitidos
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:8080',
            'http://localhost:8081',
            'https://comppare.com.br',
            'https://www.comppare.com.br',
            'https://app.comppare.com.br',
            'https://api.comppare.com.br',
        ];

        $origin = $request->headers->get('Origin');

        // Verificar se o domínio é permitido
        $isAllowed = false;
        
        if (in_array($origin, $allowedOrigins)) {
            $isAllowed = true;
        } else {
            // Verificar patterns (localhost com qualquer porta e subdomínios)
            if (preg_match('/^https?:\/\/localhost:\d+$/', $origin) || 
                preg_match('/^https?:\/\/.*\.comppare\.com\.br$/', $origin)) {
                $isAllowed = true;
            }
        }

        // Se for uma requisição OPTIONS (preflight), responder imediatamente
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Aplicar headers CORS se o domínio for permitido
        if ($isAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}

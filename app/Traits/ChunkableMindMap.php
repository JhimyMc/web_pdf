<?php

namespace App\Traits;

trait ChunkableMindMap
{
    /**
     * Dividir texto largo en chunks que quepan dentro del contexto de LM Studio.
     * Corta en puntos o espacios para mantener coherencia semántica.
     */
    protected function dividirEnChunks(string $texto, int $maxChars = 2500): array
    {
        $texto = preg_replace('/\s+/', ' ', trim($texto));
        $longitud = mb_strlen($texto);

        if ($longitud <= $maxChars) {
            return [$texto];
        }

        $chunks = [];
        $pos = 0;

        while ($pos < $longitud) {
            $fin = $pos + $maxChars;

            if ($fin < $longitud) {
                // Cortar en el último punto o espacio antes del límite
                $corte = mb_strrpos(mb_substr($texto, $pos, $maxChars), '.');
                if ($corte === false || $corte < $maxChars * 0.3) {
                    $corte = mb_strrpos(mb_substr($texto, $pos, $maxChars), ' ');
                }
                if ($corte !== false && $corte > 0) {
                    $fin = $pos + $corte + 1;
                }
            }

            $chunks[] = mb_substr($texto, $pos, $fin - $pos);
            $pos = $fin;
        }

        return $chunks;
    }
}

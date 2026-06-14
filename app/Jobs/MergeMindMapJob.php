<?php

namespace App\Jobs;

use App\Models\MindMap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MergeMindMapJob implements ShouldQueue
{
    use Queueable;

    public int $mapId;
    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(int $mapId)
    {
        $this->mapId = $mapId;
    }

    public function handle(): void
    {
        $mapa = MindMap::find($this->mapId);
        if (!$mapa) {
            Log::error("[MindMap Merge] Mapa {$this->mapId} no encontrado");
            return;
        }

        Log::info("[MindMap Merge] Fusionando resultados del mapa {$this->mapId}");

        $parciales = $mapa->partial_results ?? [];

        // Recopilar todos los nodos jerárquicos de todos los chunks
        $todosLosNodos = [];
        foreach ($parciales as $chunkIndex => $resultado) {
            if (is_array($resultado) && isset($resultado['nodos']) && is_array($resultado['nodos'])) {
                $todosLosNodos = array_merge($todosLosNodos, $resultado['nodos']);
            }
        }

        if (empty($todosLosNodos)) {
            Log::warning("[MindMap Merge] No se generaron nodos válidos para mapa {$this->mapId}");
            $mapa->update([
                'status' => 'error',
                'map_data' => ['titulo' => $mapa->title ?? 'Mapa sin datos', 'nodos' => []],
            ]);
            return;
        }

        // Aplanar la estructura jerárquica en nodos planos para d3.stratify()
        $nodosPlanos = $this->aplanarNodos($todosLosNodos);

        Log::info("[MindMap Merge] Mapa {$this->mapId} aplanado a " . count($nodosPlanos) . " nodos planos.");

        $mapData = [
            'titulo' => $mapa->title ?? 'Mapa Mental',
            'nodos'  => $nodosPlanos,
        ];

        $mapa->update([
            'status'         => 'activo',
            'map_data'       => $mapData,
            'partial_results' => null,
        ]);

        Log::info("[MindMap Merge] Mapa {$this->mapId} completado con " . count($nodosPlanos) . " nodos.");
    }

    /**
     * Aplanar nodos jerárquicos de la IA en nodos planos con id, texto, padre.
     *
     * La IA devuelve: [{"titulo": "Tema", "hijos": [{"titulo": "Subtema"}]}]
     * D3 necesita:    [{"id": "1", "texto": "Tema", "padre": null}, {"id": "2", "texto": "Subtema", "padre": "1"}]
     */
    private function aplanarNodos(array $nodos): array
    {
        $planos = [];
        $idCounter = 1;
        $titulosVistos = []; // Para deduplicar

        // Crear nodo raíz super
        $superRootId = 'root';
        $planos[] = [
            'id'     => $superRootId,
            'texto'  => 'Mapa Mental',
            'titulo' => 'Mapa Mental',
            'padre'  => null,
        ];

        foreach ($nodos as $nodo) {
            $this->procesarNodo($nodo, $superRootId, $planos, $idCounter, $titulosVistos);
        }

        // Si solo hay el nodo raíz y no se agregaron hijos, usar los nodos como raíces directamente
        if (count($planos) <= 1) {
            $planos = [];
            foreach ($nodos as $nodo) {
                $titulo = trim($nodo['titulo'] ?? $nodo['text'] ?? $nodo['name'] ?? '');
                if ($titulo === '') continue;

                $tituloLower = strtolower($titulo);
                if (in_array($tituloLower, $titulosVistos)) continue;
                $titulosVistos[] = $tituloLower;

                $nodeId = 'node_' . ($idCounter++);
                $planos[] = [
                    'id'     => $nodeId,
                    'texto'  => $titulo,
                    'titulo' => $titulo,
                    'padre'  => null,
                ];

                // Procesar hijos
                if (isset($nodo['hijos']) && is_array($nodo['hijos'])) {
                    foreach ($nodo['hijos'] as $hijo) {
                        $hijoTitulo = trim($hijo['titulo'] ?? $hijo['text'] ?? $hijo['name'] ?? '');
                        if ($hijoTitulo === '') continue;

                        $hijoLower = strtolower($hijoTitulo);
                        if (in_array($hijoLower, $titulosVistos)) continue;
                        $titulosVistos[] = $hijoLower;

                        $hijoId = 'node_' . ($idCounter++);
                        $planos[] = [
                            'id'     => $hijoId,
                            'texto'  => $hijoTitulo,
                            'titulo' => $hijoTitulo,
                            'padre'  => $nodeId,
                        ];

                        // Nietos
                        if (isset($hijo['hijos']) && is_array($hijo['hijos'])) {
                            foreach ($hijo['hijos'] as $nieto) {
                                $nietoTitulo = trim($nieto['titulo'] ?? $nieto['text'] ?? '');
                                if ($nietoTitulo === '') continue;

                                $nietoLower = strtolower($nietoTitulo);
                                if (in_array($nietoLower, $titulosVistos)) continue;
                                $titulosVistos[] = $nietoLower;

                                $planos[] = [
                                    'id'     => 'node_' . ($idCounter++),
                                    'texto'  => $nietoTitulo,
                                    'titulo' => $nietoTitulo,
                                    'padre'  => $hijoId,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $planos;
    }

    /**
     * Procesar un nodo jerárquico recursivamente.
     */
    private function procesarNodo(array $nodo, string $parentId, array &$planos, int &$idCounter, array &$titulosVistos): void
    {
        $titulo = trim($nodo['titulo'] ?? $nodo['text'] ?? $nodo['name'] ?? '');
        if ($titulo === '') return;

        $tituloLower = strtolower($titulo);
        if (in_array($tituloLower, $titulosVistos)) return;
        $titulosVistos[] = $tituloLower;

        $nodeId = 'node_' . ($idCounter++);
        $planos[] = [
            'id'     => $nodeId,
            'texto'  => $titulo,
            'titulo' => $titulo,
            'padre'  => $parentId,
        ];

        // Procesar hijos recursivamente
        if (isset($nodo['hijos']) && is_array($nodo['hijos'])) {
            foreach ($nodo['hijos'] as $hijo) {
                $this->procesarNodo($hijo, $nodeId, $planos, $idCounter, $titulosVistos);
            }
        }
    }
}

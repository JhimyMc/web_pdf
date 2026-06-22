<?php

namespace App\Console\Commands;

use App\Models\DocumentChunk;
use App\Traits\ConnectsToLMStudio;
use Illuminate\Console\Command;

class EmbedExistingChunks extends Command
{
    use ConnectsToLMStudio;

    protected $signature = 'embed:existing-chunks {--batch-size=10 : Número de chunks a procesar por lote}';
    protected $description = 'Genera embeddings para todos los document_chunks que aún no tienen embedding';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');

        $totalPendientes = DocumentChunk::whereNull('embedding')
            ->whereNotNull('chunk_text')
            ->count();

        if ($totalPendientes === 0) {
            $this->info('✅ Todos los chunks ya tienen embedding. Nada que hacer.');
            return Command::SUCCESS;
        }

        $this->info("🧠 Encontrados {$totalPendientes} chunks sin embedding. Procesando en lotes de {$batchSize}...");

        $procesados = 0;
        $fallidos = 0;

        while (true) {
            $chunks = DocumentChunk::whereNull('embedding')
                ->whereNotNull('chunk_text')
                ->limit($batchSize)
                ->get();

            if ($chunks->isEmpty()) {
                break;
            }

            $texts = $chunks->pluck('chunk_text')->toArray();
            $embeddings = $this->generateEmbeddingsBatch($texts);

            foreach ($chunks as $i => $chunk) {
                if (isset($embeddings[$i]) && is_array($embeddings[$i])) {
                    $chunk->update(['embedding' => $embeddings[$i]]);
                    $procesados++;
                } else {
                    $fallidos++;
                    $this->warn("  ⚠️ Chunk #{$chunk->id} (doc #{$chunk->document_id}): embedding nulo");
                }

                $this->line("  [{$procesados}/{$totalPendientes}] chunks procesados...", 'comment');
            }

            $this->newLine();
        }

        $this->info("✅ Completado: {$procesados} embeddings generados, {$fallidos} fallidos.");
        return Command::SUCCESS;
    }
}

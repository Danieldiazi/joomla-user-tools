<?php
namespace Danieldiazi\Component\Usertools\Administrator\Service;

/** Private reports, accessed only through the component's superuser controller. */
final class ReportStore
{
    private const HEADER = "<?php die('Forbidden.'); ?>\n";

    public function __construct(private string $directory) {}

    public function start(array $report): string
    {
        $id = gmdate('YmdHis') . '-' . bin2hex(random_bytes(12));
        $this->save($id, $report);
        return $id;
    }

    public function save(string $id, array $report): void
    {
        $target = $this->path($id);
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('No se pudo crear la carpeta de informes.');
        }
        $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.php';
        $data = self::HEADER . json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        try {
            if (file_put_contents($temporary, $data, LOCK_EX) === false || !rename($temporary, $target)) {
                throw new \RuntimeException('No se pudo guardar el informe de la tarea.');
            }
            @chmod($target, 0640);
        } finally {
            if (is_file($temporary)) { unlink($temporary); }
        }
    }

    public function get(string $id): ?array
    {
        $path = $this->path($id);
        if (!is_file($path)) { return null; }
        $content = file_get_contents($path);
        if ($content === false || !str_starts_with($content, self::HEADER)) {
            throw new \RuntimeException('El informe no se puede leer.');
        }
        $report = json_decode(substr($content, strlen(self::HEADER)), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($report)) { throw new \RuntimeException('Formato de informe incorrecto.'); }
        return $report + ['id' => $id];
    }

    public function page(int $page = 1, int $size = 20): array
    {
        $files = array_values(array_filter(glob($this->directory . '/report-*.php') ?: [],
            fn($file) => preg_match('/^report-[0-9]{14}-[a-f0-9]{24}\\.php$/D', basename($file))));
        // Timestamp + random ID keeps ordering stable for multiple runs within a second.
        rsort($files, SORT_STRING);
        $items = [];
        foreach (array_slice($files, (max(1, $page) - 1) * $size, $size) as $file) {
            $id = substr(basename($file), 7, -4);
            try {
                $report = $this->get($id);
                if ($report) { unset($report['users']); $items[] = $report; }
            } catch (\Throwable $e) {
                $items[] = ['id' => $id, 'status' => 'unreadable', 'task_title' => 'Informe no legible'];
            }
        }
        return ['items' => $items, 'total' => count($files)];
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[0-9]{14}-[a-f0-9]{24}$/D', $id)) {
            throw new \InvalidArgumentException('Identificador de informe no válido.');
        }
        return $this->directory . '/report-' . $id . '.php';
    }
}

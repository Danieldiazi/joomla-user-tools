<?php
namespace Danieldiazi\Component\Usertools\Administrator\Service;
final class InactiveSelection
{
    public static function decode(string $json,array $allowed): array
    {
        if(strlen($json)>2000000) { throw new \InvalidArgumentException('Selección demasiado grande.'); }
        try { $values=json_decode($json,false,32,JSON_THROW_ON_ERROR); }
        catch(\JsonException $e) { throw new \InvalidArgumentException('No se pudo leer la selección.'); }
        if(!is_array($values)||!array_is_list($values)) { throw new \InvalidArgumentException('Selección incorrecta.'); }
        $ids=[];
        foreach($values as $id) {
            if(!is_int($id)||$id<1||!in_array($id,$allowed,true)) { throw new \InvalidArgumentException('La selección no pertenece a esta revisión.'); }
            $ids[]=$id;
        }
        return array_values(array_unique($ids));
    }
    public static function eligibleIds(array $selected,array $preview,array $fresh): array
    {
        return array_values(array_intersect($selected,$preview,$fresh));
    }
}

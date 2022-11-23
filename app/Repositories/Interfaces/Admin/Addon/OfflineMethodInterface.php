<?php

namespace App\Repositories\Interfaces\Admin\Addon;

interface OfflineMethodInterface
{
    public function get($id);

    public function getByLang($id, $lang);

    public function store($request);

    public function all();

    public function paginate($request, $limit);

    public function update($request);

    public function statusChange($request);

    //languages functions
    public function getLanguage($id);

    public function storeLanguage($request);

    public function updateLanguage($request);

    public function activeMethods();
}

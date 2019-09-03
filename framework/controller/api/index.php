<?php

class Cj_index extends Common
{
    public function info()
    {
        $model = new indexModel();
        $info = $model->getBaseInfo();
        var_dump($info);
    }
}
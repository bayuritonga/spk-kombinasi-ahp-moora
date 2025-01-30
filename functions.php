<?php
error_reporting(~E_NOTICE);
session_start();

include 'config.php';
include 'includes/db.php';
$db = new DB($config['server'], $config['username'], $config['password'], $config['database_name']);
include 'includes/general.php';
include 'includes/paging.php';

$mod = $_GET['m'];
$act = $_GET['act'];

$nRI = array(
    1 => 0,
    2 => 0,
    3 => 0.58,
    4 => 0.9,
    5 => 1.12,
    6 => 1.24,
    7 => 1.32,
    8 => 1.41,
    9 => 1.46,
    10 => 1.49,
    11 => 1.51,
    12 => 1.48,
    13 => 1.56,
    14 => 1.57,
    15 => 1.59
);

$rows = $db->get_results("SELECT kode_alternatif, nama_alternatif FROM tb_alternatif ORDER BY kode_alternatif");
foreach ($rows as $row) {
    $ALTERNATIF[$row->kode_alternatif] = $row->nama_alternatif;
}

$rows = $db->get_results("SELECT kode_kriteria, nama_kriteria, atribut FROM tb_kriteria ORDER BY kode_kriteria");
foreach ($rows as $row) {
    $KRITERIA[$row->kode_kriteria] = $row;
}

function get_rel_kriteria()
{
    global $db;
    $arr = array();
    $rows = $db->get_results("SELECT * FROM tb_rel_kriteria ORDER BY ID1, ID2");
    foreach ($rows as $row) {
        $arr[$row->ID1][$row->ID2] = $row->nilai;
    }
    return $arr;
}

function get_relalternatif($kriteria = '')
{
    global $db;
    $rows = $db->get_results("SELECT * FROM tb_rel_alternatif WHERE kode_kriteria='$kriteria' ORDER BY kode1, kode2");
    $matriks = array();
    foreach ($rows as $row) {
        $matriks[$row->kode1][$row->kode2] = $row->nilai;
    }
    return $matriks;
}

function get_kriteria_option($selected = 0)
{
    global $KRITERIA;
    $a = '';
    foreach ($KRITERIA as $key => $val) {
        if ($key == $selected)
            $a .= "<option value='$key' selected>$val->nama_kriteria</option>";
        else
            $a .= "<option value='$key'>$val->nama_kriteria</option>";
    }
    return $a;
}

function get_atribut_option($selected = '')
{
    $atribut = array('benefit' => 'Benefit', 'cost' => 'Cost');
    $a = '';
    foreach ($atribut as $key => $val) {
        if ($selected == $key)
            $a .= "<option value='$key' selected>$val</option>";
        else
            $a .= "<option value='$key'>$val</option>";
    }
    return $a;
}

function get_alternatif_option($selected = '')
{
    global $db;
    $rows = $db->get_results("SELECT kode_alternatif, nama_alternatif FROM tb_alternatif ORDER BY kode_alternatif");
    $a = '';
    foreach ($rows as $row) {
        if ($row->kode_alternatif == $selected)
            $a .= "<option value='$row->kode_alternatif' selected>$row->kode_alternatif - $row->nama_alternatif</option>";
        else
            $a .= "<option value='$row->kode_alternatif'>$row->kode_alternatif - $row->nama_alternatif</option>";
    }
    return $a;
}

function get_nilai_option($selected = '')
{
    $nilai = array(
        '1' => 'Sama penting dengan',
        '2' => 'Mendekati sedikit lebih penting dari',
        '3' => 'Sedikit lebih penting dari',
        '4' => 'Mendekati lebih penting dari',
        '5' => 'Lebih penting dari',
        '6' => 'Mendekati sangat penting dari',
        '7' => 'Sangat penting dari',
        '8' => 'Mendekati mutlak dari',
        '9' => 'Mutlak sangat penting dari',
    );
    $a = '';
    foreach ($nilai as $key => $val) {
        if ($selected == $key)
            $a .= "<option value='$key' selected>$key - $val</option>";
        else
            $a .= "<option value='$key'>$key - $val</option>";
    }
    return $a;
}

function get_rel_alternatif()
{
    global $db;
    $rows = $db->get_results("SELECT * FROM tb_rel_alternatif ORDER BY kode_alternatif, kode_kriteria");
    $arr = array();
    foreach ($rows as $row) {
        $arr[$row->kode_alternatif][$row->kode_kriteria] = $row->nilai;
    }
    return $arr;
}
/**
 * Membuat opsi level
 *
 * @param       string  $selected   Level terpilih 
 * @return      string  
 */
function get_level_option($selected = '')
{
    $arr = array(
        'admin' => 'Admin',
        'user' => 'User',
    );
    $a = '';
    foreach ($arr as $key => $val) {
        if ($selected == $key)
            $a .= "<option value='$key' selected>$val</option>";
        else
            $a .= "<option value='$key'>$val</option>";
    }
    return $a;
}
class AHP
{
    function __construct($data)
    {
        $this->data = $data;
        $this->baris_total();
        $this->normal();
        $this->prioritas();
        $this->cm();
    }
    function baris_total()
    {
        $this->baris_total = array();
        foreach ($this->data as $key => $val) {
            foreach ($val as $k => $v) {
                $this->baris_total[$k] += $v;
            }
        }
    }
    function normal()
    {
        $this->normal = array();
        foreach ($this->data as $key => $val) {
            foreach ($val as $k => $v) {
                $this->normal[$key][$k] = $v / $this->baris_total[$k];
            }
        }
    }
    function prioritas()
    {
        $this->prioritas = array();
        foreach ($this->normal as $key => $val) {
            $this->prioritas[$key] = array_sum($val) / count($val);
        }
    }
    function cm()
    {
        $this->cm = array();
        foreach ($this->data as $key => $val) {
            foreach ($val as $k => $v) {
                $this->cm[$key] += $v * $this->prioritas[$k];
            }
            $this->cm[$key] /= $this->prioritas[$key];
        }
    }
}

class MOORA
{
    function __construct($rel_alternatif, $bobot, $atribut)
    {
        $this->rel_alternatif = $rel_alternatif;
        $this->bobot = $bobot;
        $this->atribut = $atribut;

        $this->hitung();
    }

    function hitung()
    {
        $this->normal();
        $this->terbobot();
        $this->total();
        $this->rank();
    }

    function rank()
    {
        $temp = $this->total;
        arsort($temp);
        $no = 1;
        $this->rank = array();
        foreach ($temp as $key => $value) {
            $this->rank[$key] = $no++;
        }
    }

    function total()
    {
        $this->total = array();
        foreach ($this->terbobot as $key => $val) {
            $this->total[$key] = array_sum($val);
        }
    }

    function terbobot()
    {
        $this->terbobot = array();
        foreach ($this->normal as $key => $val) {
            foreach ($val as $k => $v) {
                $this->terbobot[$key][$k] = $v * $this->bobot[$k] * (strtolower($this->atribut[$k]) == 'benefit' ? 1 : -1);
            }
        }
    }

    function normal()
    {
        $arr = array();
        foreach ($this->rel_alternatif as $key => $val) {
            foreach ($val as $k => $v) {
                $arr[$k] += $v * $v;
            }
        }
        $this->normal = array();
        foreach ($this->rel_alternatif as $key => $val) {
            foreach ($val as $k => $v) {
                $this->normal[$key][$k] = $v / sqrt($arr[$k]);
            }
        }
    }
}

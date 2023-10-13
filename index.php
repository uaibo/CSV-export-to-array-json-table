<?php

class Csv {

    // customize these values
    private $filename = 'list.csv'; //'your_file_path.csv';
    private $delimiter = ';';
    private $first_row_is_headers = true;
    private $key_prepend = "col_";


    // don't touch these
    private $data_keys = [];
    private $head = [];
    private $body = [];
    private $columns_dirty = [];

    private $tableHeaders = [];
    public function __construct($filename=null, $delimiter=null, $first_is_headers=true)
    {
        if( $filename ){
            $this->filename = $filename;
        }

        if( $delimiter ){
            $this->delimiter = $delimiter;
        }

        $this->first_row_is_headers = $first_is_headers;

        $this->process_csv();
    }

    function process_csv()
    {
        if( !$this->filename || !file_exists($this->filename) )
        {
            die('Please specify a CSV filename and make sure it exists.');
        }

        $file_content = fopen($this->filename, 'r');

        $index = 0;
        while(! feof($file_content))
        {
            $row = fgetcsv($file_content, 0, $this->delimiter);

            if( $index==0 && $this->first_row_is_headers ){
                $this->setHeaders($row);

                $index++;
                continue;
            }

            $this->setContent($row);

            $index++;
        }
        fclose($file_content);
    }

    function setHeaders($row)
    {
        foreach($row as $index=>$val)
        {
            $header_row_key = $this->key_prepend . $index;

            $this->head[] = [
                'key' => $header_row_key,
                'value' => $this->cleanString($val)
            ];
        }

        // set default dirty columns
        $this->setupDirtyColumnIndexes($row);
    }

    function setContent($row)
    {
        $content_row = [];
        foreach((array)$row as $index=>$val)
        {
            if( !count($this->columns_dirty) )
            {
                // set default dirty columns
                $this->setupDirtyColumnIndexes($row);
            }

            $content_row_key = $this->key_prepend . $index;

            $content_row[$content_row_key] = $val;

            // if any row column has content on this column, it is dirty
            if( strlen(trim($val)) ){
                $this->columns_dirty[$index] = true;
            }
        }

        $this->body[] = $content_row;
    }

    function setupDirtyColumnIndexes($row)
    {
        if( count($this->columns_dirty) )
        {
            return;
        }

        foreach( $row as $index => $header )
        {
            $this->columns_dirty[$index] = false; //default empty column
        }
    }
    function getAsArray()
    {
        $this->cleanEmptyColumns();

        return [
            'head' => $this->head,
            'body' => $this->body
        ];
    }

    function getAsHtmlTable()
    {
        $asArray = $this->getAsArray();
        $head = $asArray['head'];
        $body = $asArray['body'];

        $html = '';
        $html .= '<table border="1" style="border-collapse: collapse;">';

        if( count($head) ){
            $html .= '<thead>';
            $html .=    '<tr>';
            foreach($head as $header)
            {
                $html .= '<th>' . $header['value'] . '</th>';
            }
            $html .=    '</tr>';
            $html .= '</head>';
        }

        if( count($body) ){
            $html .= '<tbody>';
            foreach($body as $row)
            {
                $html .= '<tr>';

                foreach($row as $key => $value)
                {
                    $html .=    '<td>' . $value . '</td>';
                }

                $html .= '</tr>';
            }
            $html .= '</tbody>';
        }

        header('Content-Type: text/html; charset=utf-8');
        return $html;
    }

    function getAsJson()
    {
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($this->getAsArray());
    }

    function cleanEmptyColumns()
    {
        $this->cleanEmptyHeaders();
        $this->cleanEmptyRows();
    }

    function cleanEmptyHeaders()
    {
        foreach( $this->columns_dirty as $index => $is_dirty)
        {
            if( ! $is_dirty ){
                unset($this->head[$index]);
            }
        }
    }

    function cleanEmptyRows()
    {
        foreach( $this->columns_dirty as $index => $is_dirty)
        {
            if( ! $is_dirty ){
                foreach( $this->body as $row_index => $row )
                {
                    $row_key = $this->key_prepend . $index;
                    unset($this->body[$row_index][$row_key]);
                }
            }
        }
    }

    function cleanString($string)
    {
        return preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $string);
    }
}

$csv = new Csv();
echo $csv->getAsJson();
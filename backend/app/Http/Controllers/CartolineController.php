<?php

namespace App\Http\Controllers;

use App\Models\Cartoline;
use Illuminate\Http\Request;
use App\Exports\CartolineExport;
use Maatwebsite\Excel\Facades\Excel;

class CartolineController extends Controller
{
    public function __construct()
    {
        $this->middleware("auth"); 
        $this->middleware("auth.revisor");
    }

    protected function findCartoline($id) 
    {
        return Cartoline::find($id);
    }

    protected function getFormData($req) {
        return [
            "descrizione_mandante" => $req->descrizione_mandante,
            "codice_mandate" => $req->codice_mandate,
            "nome_cognome_debitore" => $req->nome_cognome_debitore,
            "cf_piva_debitore" => $req->cf_piva_debitore,
            "ndg" => $req->ndg,
            "data_spedizione" => $req->data_spedizione,
            "numero_raccomandata" => $req->numero_raccomandata,
            "data_notifica" => $req->data_notifica,
            "esito_notifica" => $req->esito_notifica,
            "fase" => $req->fase,
            "chiave_pratica"=> $req->chiave_pratica,
        ];
    }

    public function cartoline() 
    {
        $cartoline = Cartoline::orderBy("created_at")->limit(15)->get();;

        return view("cartoline.cartoline", compact('cartoline'));
    }

    public function detailCartoline($id)
    {
        $cartolina = $this->findCartoline($id);
       
        return view("cartoline.detailCartoline", compact('cartolina'));
    }

    public function cartolineForm($id = null) 
    {
        if ($id) {
            $cartolina = $this->findCartoline($id);
            
            return view("cartoline.cartolineForm", compact('cartolina'));
        }
        return view("cartoline.cartolineForm");

    }

    public function createCartolina(Request $request, $id = null){
        $formData = $this->getFormData($request);
        $fileName = '';
        $path_file = '';

        $esiti_obj = [
            "ricevuto_destinatario",
            "ricevuto_familiare_conveniente", 
            "ricevuto_addetto_alla_casa_ufficio_o_azienda",
            "ricevuto_portiere_dello_stabile",
            "ritirato", 
            "compiuta_giacenza", 
            "rifiutato", 
            "destinatario_irreperibile", 
            "destinatario_deceduto", 
            "destinatario_sconosciuto", 
            "destinatario_trasferito", 
            "indirizzo_inesatto", 
            "indirizzo_insufficiente", 
            "indirizzo_inesistente", 
            'CAD',
        ];
        
        foreach ($esiti_obj as $value) {
            $lenghtValueArr = array_search($value, $esiti_obj);
            $folderName = '';

            if ($value == $request->esito_notifica && $request->hasFile('nome_file')) {

                if ($lenghtValueArr <= 6) {
                    $folderName = 'OK';
                } elseif ($lenghtValueArr == 14) {
                    $folderName = 'Cartoline';
                } elseif ($lenghtValueArr > 6 || $lenghtValueArr < 14) {
                    $folderName = 'KO';
                }

                $file = $request->nome_file;
                $fileName = $file->getClientOriginalName();
                $date = date('Ymd');
            
                $path_file = $file->store('public/'.$date.'/'.$folderName.'/'.$fileName);  
            }  
        }

        if ($id) {
            $formatData = [
                "data_notifica" => $request->data_notifica,
                "esito_notifica" => $request->esito_notifica,
            ];
            
            $cartoline = $this->findCartoline(intval($id)); 

            $formatData = array_merge($formatData, ['path_file' => $path_file]);
            $formatData = array_merge($formatData, ['nome_file' => $fileName]);

            $cartoline->update($formatData);

            return redirect("/detailCartoline/" . $id)->with("id", $id);
        }

        $formData = array_merge($formData, ['path_file' => $path_file]);
        $formData = array_merge($formData, ['nome_file' => $fileName]);
        
        $cartoline = Cartoline::create($formData);
        $ultimo_cartolina = Cartoline::orderBy("created_at", "desc")->first();
        $id = $ultimo_cartolina->id;
        
        return redirect("/detailCartoline/".$id);
    }

    public function searchCartolina(Request $request)
    {
        $query = $request->input("query");

        $cartoline = Cartoline::search($query)->get();

        if ($query) {
            return view("cartoline.search", compact("cartoline", "query"));
        }
    }

    public function importCsv(Request $request){
    
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt,xlsx,xls'
        ]);
    
        $file = file($request->file('csv_file')->getRealPath());
        //it start from row 1
        $data = array_slice($file, 1);
        //allowing php to process the data in chunks
        $parts = (array_chunk($data, 2000));

        foreach ($parts as $index => $part) {
            $fileName = resource_path('pending-files/'.date('y-m-d-H-i-s').$index. '.csv');
            file_put_contents($fileName, $part);
        }
       
        (new Cartoline())->importToDb();

        session()->flash('status', 'queued for importing!');
        return redirect(route('cartoline'));

    } 
     public function exportExcel()
     {
        return Excel::download(new CartolineExport, 'cartoline.xlsx');
     }
}

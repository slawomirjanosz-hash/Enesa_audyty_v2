"""Convert supplied HTML JSON into inert, reviewed form schemas (never execute scripts)."""
import html, json, re, hashlib, pathlib, sys

SOURCE = pathlib.Path(sys.argv[1])
DEST = pathlib.Path(__file__).resolve().parents[1] / 'resources/iso50001'
LABELS = dict(line.split('=', 1) for line in '''META=Metryka i zasady
KARTY=Karty
PROJEKTY=Analizy projektów
ZAKUPY=Oceny zakupów
KATEGORIE=Kryteria kategorii zakupowych
USTALENIA=Co, jak i kiedy monitorować
MODEL=Model zużycia oczekiwanego
OKRESY=Zużycie rzeczywiste i oczekiwane
WSKAZNIKI=Wyniki wskaźników EnPI
SEU=Nadzór nad znaczącymi użyciami energii
ODCH=Odchylenia i reakcje
OCENA_E=Ocena i rekomendacje
WYM=Ocena wymagań z punktu 4.2
OCENA=Wnioski z oceny zgodności
PROGRAM=Program audytów
RAPORTY=Raporty audytów wewnętrznych
WEJSCIA=Dane wejściowe przeglądu
DECYZJE=Decyzje kierownictwa
OCENA_C=Ocena ogólna systemu
POZYCJE=Plan doskonalenia
ZRODLA=Źródła możliwości poprawy
MOZLIWOSCI=Ocena możliwości poprawy
POPRAWA=Wykazanie poprawy wyniku
A=Rozpoznanie wpływu
B=Możliwości poprawy i ocena
C=Nadzór operacyjny / wymagania dla dostawców
D=Wprowadzenie wyników do projektu
E=Odbiór i sprawdzenie po uruchomieniu
seu=Znaczące użycie energii / proces
lokalizacja=Lokalizacja
zuzycie=Zużycie i udział w zużyciu zakładu
osoby=Osoby wpływające na wynik energetyczny
wskaznik=Wskaźnik i wartość odniesienia
utworzono=Data utworzenia
aktualizacja=Data aktualizacji
opracowal=Opracował
zatwierdzil=Zatwierdził
kryteria=Kryteria
parametr=Parametr
potrzeba=Potrzeba procesu
straty=Straty i zapas
kryterium=Kryterium
kontrola=Sposób kontroli
tryby=Tryby pracy
tryb=Tryb
jak=Sposób wykonania / sprawdzenia
uwagi=Uwagi
zmiany=Nadzór nad zmianami i wykonawcami
ktoZmienia=Kto może zmieniać nastawy i za czyją zgodą
wykonawcy=Wymagania wobec wykonawców
planowane=Nadzór nad zmianami planowanymi
niezamierzone=Przegląd i ograniczenie skutków zmian niezamierzonych
zapisZmian=Miejsce zapisu zmian
sygnaly=Sygnały wymagające reakcji
sygnal=Sygnał
prog=Próg
kolejnosc=Kolejność sprawdzeń
kto=Osoba odpowiedzialna
zglasza=Komu i kiedy zgłosić
zapis=Miejsce zapisu / dowód
utrzymanie=Utrzymanie ruchu
czynnosc=Czynność
czestotliwosc=Częstotliwość / warunek
wykonuje=Wykonuje
przekazanie=Przekazanie kryteriów
komu=Odbiorca
wykonawca=Wykonawca zewnętrzny
data=Data
sprawdzenie=Sprawdzenie zrozumienia / skuteczności
przeglad=Termin lub rytm przeglądu
nazwa=Nazwa
nr=Stały numer / identyfikator
kierownik=Kierownik projektu
etap=Etap projektu
etapAnalizy=Etap przeprowadzenia analizy
ktoAnaliza=Osoba przeprowadzająca analizę
wartosc=Wartość
uzasadnienieA=Uzasadnienie zakończenia oceny na etapie A
wnioski=Wnioski projektowe
tresc=Treść
decyzja=Decyzja
dlaczego=Uzasadnienie
gdzie=Miejsce wprowadzenia do projektu / specyfikacji
ktoAktualizuje=Kto i kiedy aktualizuje kryteria operacyjne
warunki=Warunki porównania
termin=Termin i odpowiedzialny
przedmiot=Przedmiot zakupu
zrodloWymagan=Źródło wymagań
status=Status
wymagania=Wymagania minimalne
skad=Źródło wymagania
informacja=Informacja dla dostawców o kryterium energetycznym
kryteriumWyboru=Kryterium wyboru oferty
lata=Okres użytkowania (lata)
cenaEnergii=Cena energii (zł/MWh)
zalozenia=Założenia porównania
oferty=Porównywane oferty
minimalne=Spełnia wymagania minimalne
cena=Cena zakupu (zł)
roczne=Zużycie roczne (MWh)
moc=Średnia moc (kW)
godziny=Czas pracy (h/rok)
jednostkowe=Zużycie jednostkowe (kWh/jednostkę)
ilosc=Roczna liczba jednostek
zrodloDanych=Źródło danych o zużyciu i profil pracy
inne=Inne koszty w całym okresie (zł)
rekomendacja=Rekomendacja
porownanieNd=Porównanie kosztu energii nie dotyczy
porownanieNdPowod=Uzasadnienie odstąpienia od porównania
deklaracje=Sprawdzenie deklaracji przy odbiorze
wynik=Wynik
kategoria=Kategoria
minimum=Wymaganie minimalne
dane=Dane wymagane od dostawcy
podstawa=Podstawa ustalenia kryterium
kod=Identyfikator
co=Co monitorujemy
zrodlo=Źródło
metoda=Metoda
ktoMierzy=Kto mierzy
czestoscPomiar=Częstotliwość pomiarów
ktoAnalizuje=Kto analizuje
czestoscAnaliza=Częstotliwość analizy
a=Współczynnik a
opisA=Zmienna x1 i jednostka
b=Współczynnik b
opisB=Zmienna x2 i jednostka
c=Wyraz wolny c
okres=Okres
x1=Wartość zmiennej x1
x2=Wartość zmiennej x2
rzeczywiste=Zużycie rzeczywiste (MWh)
rozbicie=Rozbicie na nośniki / źródła
komentarz=Komentarz
kierunek=Kierunek poprawy
enb=Wartość linii bazowej EnB
enbOkres=Okres linii bazowej
biez=Wartość w okresie
biezOkres=Okres bieżący
cel=Cel
celTermin=Termin celu
ocena=Ocena
obszar=Obszar
stan=Stan
kiedy=Kiedy stwierdzono
przyczyna=Przyczyna
reakcja=Reakcja / korekcja
niezgodnosc=Czy odchylenie stanowi niezgodność
dzialanie=Działanie
system=Ocena skuteczności systemu
rekomendacje=Rekomendacje
rytmSystem=Rytm oceny skuteczności systemu
sporzadzil=Sporządził
odbiorcy=Odbiorcy raportu
dataZatw=Data zatwierdzenia
strona=Strona zainteresowana
wymaganie=Wymaganie
kwalifikacja=Kwalifikacja wymagania
dowod=Dowód / dokument źródłowy
karta10=Numer karty niezgodności (10.1)
odp=Odpowiedzialny i termin
naPrzeglad=Wnioski na przegląd zarządzania
przegladStanu=Sposób śledzenia zmian stanu prawnego
dataOceny=Data oceny
stanPrawny=Stan prawny na dzień
oceniajacy=Oceniający
rytmBaza=Częstotliwość podstawowa
rytm=Opis rytmu i dodatkowych ocen
wRejestrze=Liczba wymagań w rejestrze
nieprzyjete=Liczba oczekiwań nieprzyjętych
punkty=Punkty normy
audytor=Audytor wewnętrzny
pozycja=Numer pozycji programu
daty=Terminy prowadzenia audytu
zakres=Zakres audytu
wlasnaPraca=Czy audytor ocenia własną pracę
bezstronnosc=Zapewnienie bezstronności
audytowani=Audytowane osoby / funkcje
rozdzielnik=Przekazanie wyników kierownictwu
ustalenia=Ustalenia z audytu
rodzaj=Rodzaj ustalenia
opis=Opis
uwagiProgram=Wnioski do kolejnego programu audytów
organizacja=Organizacja i zakres programu
organizuje=Organizuje
planAudytu=Planowanie i przekazywanie planu audytu
podsumowanie=Podsumowanie danych wejściowych
uzasadnienie=Uzasadnienie
przydatnosc=Ocena przydatności, adekwatności i skuteczności systemu
forma=Forma przeglądu
uczestnicy=Uczestnicy
najwyzszeKier=Udział najwyższego kierownictwa
przygotowal=Przygotował
terminBudzetu=Termin zamknięcia budżetu
statusDecyzji=Sposób sprawdzania realizacji decyzji
nastepnyPrzeglad=Termin następnego przeglądu
pierwszy=Czy to pierwszy przegląd
podpis=Osoba zatwierdzająca protokół
dataWykrycia=Data wykrycia
ponownieOtwarta=Data ponownego otwarcia
klasyfikacja=Klasyfikacja organizacji (opcjonalnie)
korekcja=Korekcja i opanowanie niezgodności
korekcjaKto=Kto i kiedy wykonał korekcję
konsekwencje=Konsekwencje
nadwyzkaMWh=Nadwyżka zużycia (MWh)
skalaSkutkow=Skala skutków
przebieg=Przebieg analizy przyczyny
podobneSprawdzono=Gdzie jeszcze sprawdzono podobne przypadki
podobneZnalezione=Znaleziono podobne przypadki
uzasadnienieZakres=Uzasadnienie zakresu działań
zakresDzialania=Zakres działania
dzialania=Działania korygujące
wykonano=Stan wykonania / data
zmianaSystemu=Czy potrzebna jest zmiana systemu
zmianaOpis=Opis zmiany systemu
dowodPlanowany=Planowany dowód skuteczności
terminDowodu=Najwcześniejszy termin uzyskania dowodu
dataSprawdzenia=Data sprawdzenia skuteczności
wynikSprawdzenia=Wynik sprawdzenia skuteczności
sprawdzil=Sprawdził
efekt=Oczekiwany efekt i stan wyjściowy
weryfikacja=Sposób weryfikacji efektu
osiagniety=Osiągnięty efekt / powód anulowania
coWplynelo=Co wpłynęło do planu
potencjalMWh=Potencjał oszczędności (MWh/rok)
nosnik=Nośnik energii
koszt=Koszt inwestycji (zł)
jakWykazac=Jak wykazać efekt
ryzyko=Ryzyko
powod=Uzasadnienie decyzji
powodBraku=Powód braku wartości
wyjasnienie=Wyjaśnienie pogorszenia
cenaEl=Cena energii elektrycznej (zł/MWh)
cenaGaz=Cena gazu (zł/MWh)
potencjalRazem=Całkowity potencjał z przeglądu (MWh/rok)
o=Odpowiedź
u=Uzasadnienie / dowód
poprawa=Poprawa wyniku energetycznego
zgodnosc=Zgodność z wymaganiami
wdrozenie=Skuteczne wdrożenie i utrzymanie
z_wartosci=Wartość przed zmianą
na_wartosc=Wartość po zmianie
zgoda=Zgoda na zmianę
rejestrZmian=Rejestr zmian nastaw
brakZdarzen=Nie wystąpiły zdarzenia w tym okresie
uzasadnienieBraku=Uzasadnienie braku zdarzeń i zakres sprawdzenia'''.splitlines())
NUMBERS=set('lata cenaEnergii cena roczne moc godziny jednostkowe ilosc inne a b c x1 x2 rzeczywiste enb biez nadwyzkaMWh potencjalMWh koszt cenaEl cenaGaz potencjalRazem'.split())
OPTIONAL=set('uwagi komentarz rozbicie ponownieOtwarta klasyfikacja nadwyzkaMWh koszt potencjalRazem'.split())
LABELS.update(dict(line.split('=', 1) for line in '''WEW=Komunikacja wewnętrzna
ZEW=Komunikacja zewnętrzna
PROCES=Zgłaszanie uwag i propozycji
ZGLOSZENIA=Rejestr zgłoszeń
WYKAZ=Wykaz dokumentów i zapisów
ZASADY=Zasady nadzoru nad dokumentacją
ZEWN=Dokumenty zewnętrzne
ktoMoze=Kto może zgłaszać uwagi i propozycje
kanaly=Kanały zgłaszania
potwierdzenie=Potwierdzenie przyjęcia zgłoszenia
dni=Termin potwierdzenia w dniach roboczych
rozpatrzenie=Rozpatrywanie zgłoszeń
zwrotna=Informacja zwrotna
rejestr=Miejsce zapisu zgłoszeń
zglaszajacy=Zgłaszająca osoba lub funkcja
kanal=Kanał zgłoszenia
odpowiedz=Data odpowiedzi
odpowiedzTresc=Treść odpowiedzi
tytul=Tytuł dokumentu
punkt=Punkt normy
wersja=Wersja
miejsce=Miejsce przechowywania
identyfikacja=Identyfikacja i opis dokumentów
zatwierdzanie=Przegląd i zatwierdzanie
dostepnosc=Dostępność i dystrybucja
ochrona=Ochrona dokumentów i zapisów
przechowywanie=Przechowywanie i usuwanie
zewnetrzne=Nadzór nad dokumentami zewnętrznymi
dotyczy=Zastosowanie dokumentu
sprawdzono=Data sprawdzenia aktualności'''.splitlines()))
NUMBERS.add('dni')
YES={'tak':'Tak','nie':'Nie'}
OPTIONS={'o':dict(YES,nd='Nie dotyczy'), 'wykonawca':YES,'porownanieNd':YES,'niezgodnosc':YES,'wlasnaPraca':YES,'najwyzszeKier':YES,'pierwszy':YES,'podobneZnalezione':YES,'zmianaSystemu':YES,'minimalne':YES,'brakZdarzen':YES,
 'kierunek':{'nizej':'Niższy wynik jest lepszy','wyzej':'Wyższy wynik jest lepszy'},'etap':{'zalozenia':'Założenia','koncepcja':'Koncepcja','zatwierdzenie':'Zatwierdzenie','realizacja':'Realizacja','uruchomione':'Uruchomiono'},'etapAnalizy':{'zalozenia':'Założenia','koncepcja':'Koncepcja','zatwierdzenie':'Zatwierdzenie','realizacja':'Realizacja'},'skalaSkutkow':{'male':'Małe','srednie':'Średnie','duze':'Duże'},'zakresDzialania':{'miejsce':'Miejsce wykrycia','szerszy':'Szerszy zakres'},'nosnik':{'el':'Energia elektryczna','gaz':'Gaz','inne':'Inny'},'rytmBaza':{'czesciej':'Częściej niż raz w roku','rocznie':'Raz w roku','rzadziej':'Rzadziej niż raz w roku'}}

def merge(a,b):
    if isinstance(a,dict) and isinstance(b,dict):
        for k,v in b.items(): a[k]=merge(a[k],v) if k in a else v
    elif isinstance(a,list) and isinstance(b,list): a+=b
    return a

unknown=set()
for f in sorted(SOURCE.glob('ENESA_*.html')):
    m=re.match(r'ENESA_((?:(?:8|9|10)\.[\d.]+|7\.[45]))_',f.name)
    if not m: continue
    section=m[1].replace('.','-'); raw=f.read_text(encoding='utf-8'); block=re.search(r'<pre id=[\'"]js[\'"]>(.*?)</pre>',raw,re.S)
    if not block: raise ValueError(f)
    source=json.loads(html.unescape(block[1])); example=source['przyklad']
    # Merge all examples: later rows can contain fields absent in the first one.
    def node(v,k,path):
        label=LABELS.get(k)
        if not label: unknown.add(k); label=k
        if isinstance(v,list):
            item={}
            for row in v:
                if isinstance(row,dict): item=merge(item,row)
            return {'type':'list','label':label,'min':0,'children':{x:node(y,x,path+'.*.'+x) for x,y in item.items()}}
        if isinstance(v,dict):
            return {'type':'object','label':label,'children':{x:node(y,x,path+'.'+x) for x,y in v.items()}}
        typ='number' if k in NUMBERS else ('date' if re.fullmatch(r'\d{4}-\d{2}-\d{2}',str(v)) or k in ['dataSprawdzenia','terminDowodu','ponownieOtwarta'] else 'textarea')
        opts=OPTIONS.get(k,{})
        if k=='kierunek' and section=='9-3': opts={}
        if k=='tryb' and section=='8-3': opts={'roczne':'Roczne zużycie MWh','moc':'Średnia moc × godziny','jednostkowy':'kWh/jednostkę × ilość'}
        if path.endswith('.wnioski.*.decyzja'): opts={'przyjety':'Przyjęty','odrzucony':'Odrzucony'}
        if section=='9-1-2' and k=='wynik': opts={'zgodne':'Zgodne','wtoku':'W toku zgodnie z planem','douzupelnienia':'Do uzupełnienia dowodem','niezgodne':'Niezgodne'}
        if section=='9-2' and k=='rodzaj': opts={'N':'Niezgodność','D':'Możliwość doskonalenia','P':'Pozytywne ustalenie'}
        if section=='9-2' and k=='zrodlo': opts={x:x.capitalize() for x in ['dokument','rozmowa','obserwacja','pomiar']}
        if section=='9-2' and k=='status': opts={'planowany':'Planowany','wtoku':'W toku','wykonany':'Wykonany','odwolany':'Odwołany'}
        if section=='10-1' and k=='decyzja': opts={'kontynuowac':'Kontynuować','zamknac':'Zamknąć po ocenie skuteczności','bez':'Zamknąć bez działania — uzasadnij'}
        if section=='10-1' and k=='metoda': opts={'jedno':'Jedno dlaczego','5x':'5 × dlaczego','ishikawa':'Ishikawa','inna':'Inna'}
        if section=='10-1' and k=='zrodlo': opts={x:y for x,y in [('monitorowanie','Monitorowanie'),('zgodnosc','Ocena zgodności'),('audyt','Audyt wewnętrzny'),('zgloszenie','Zgłoszenie'),('inne','Inne')]}
        if section=='10-2' and k=='obszar': opts={'S':'System','W':'Wynik energetyczny'}
        if section=='10-2' and k=='stan': opts={'P':'Planowane','W':'W toku','Z':'Zakończone','A':'Anulowane'}
        if section=='10-2' and k=='decyzja': opts={'plan_dzialan':'Plan działań 6.2','plan_dosk':'Plan doskonalenia','odlozona':'Odłożona','odrzucona':'Odrzucona'}
        if section=='9-1-1' and k=='stan': opts={'spelnione':'Spełnione','czesciowo':'Częściowo spełnione','poza':'Poza zakresem oceny w okresie','niespelnione':'Niespełnione','brak':'Brak danych'}
        if section in ['7-4','7-5']:
            opts={}
            if section=='7-4' and k=='rodzaj': opts={'obowiazek':'Obowiązek — przepis lub umowa','polityka':'Polityka energetyczna','dobrowolna':'Komunikacja dobrowolna'}
            if section=='7-4' and k=='decyzja': opts={'wdrozone':'Wdrożone','odroczone':'Odroczone','odmowa':'Uzasadniona odmowa','wtoku':'W toku'}
            if section=='7-5' and k=='rodzaj': opts={'D':'Dokument','Z':'Zapis','DZ':'Dokument i zapis'}
            if section=='7-5' and k=='podstawa': opts={'N':'Wymaganie normy','M':'Potrzeba organizacji'}
            if k in ['sprawdzono','odpowiedz'] or (section=='7-4' and k=='potwierdzenie' and path.startswith('ZGLOSZENIA.')): typ='date'
        required=k not in OPTIONAL
        if section=='7-4' and k in ['podstawa','efekt']: required=False
        if section=='7-5' and k in ['wersja','punkt']: required=False
        return {'type':'select' if opts else typ,'label':label,'required':required,'options':opts}
    tree={k:node(v,k,k) for k,v in example.items()}
    for k in ['wRejestrze','nieprzyjete']: tree.get('META',{}).get('children',{}).pop(k,None)
    if section=='8-3':
        offer=tree['ZAKUPY']['children']['oferty']['children']
        for k in ['moc','godziny','jednostkowe','ilosc']: offer[k]=node('',k,'ZAKUPY.*.oferty.*.'+k)
    if section in ['8-2','8-3','10-1']:
        for k in ['brakZdarzen','uzasadnienieBraku']: tree['META']['children'][k]=node('',k,'META.'+k)
    # Stable fixed dictionaries retain every required source code, not example answers.
    definitions={k:v for k,v in source.items() if k!='przyklad'}
    for root,defkey in [('USTALENIA','CHARAKTERYSTYKI'),('WEJSCIA','WEJSCIA_DEF'),('DECYZJE','OBSZARY_DEF'),('ZRODLA','ZRODLA_DEF')]:
        if root in tree and defkey in definitions: tree[root]['codes']={x['kod']:x.get('nazwa',x.get('tekst',x['kod'])) for x in definitions[defkey]}
    for root,parts in [('PROJEKTY',['A','B','C','D']),('ZAKUPY',['A','C']),('RAPORTY',['B'])]:
        if root not in tree: continue
        for part in parts:
            dk=('PYT_Z'+part if root=='ZAKUPY' else 'PYT_B9' if root=='RAPORTY' else 'PYT_'+part)
            for q in definitions.get(dk,[]):
                target=tree[root]['children'].get(part,{}).get('children',{}).get(q['kod'])
                if target: target['label']=q['tekst']; target['help']=q.get('pomoc','')
    data={'version':1,'section':section,'source_file':f.name,'source_sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'definitions':definitions,'tree':tree}
    (DEST/('execution-'+section+'-v1.json')).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(section, 'imported')
print('UNLABELLED',sorted(unknown))

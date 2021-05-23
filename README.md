# doctolib-autobooking

Réserve un rendez-vous au hazard pour la vaccination COVID-19 parmi un ou plusieurs centres de vaccination.

## Usage

Clonez le répo puis exécutez la commande avec [Docker](https://www.docker.com/).


```sh
$ git clone git@github.com:julienbornstein/doctolib-autobooking.git
$ cd doctolib-autobooking
$ docker build -t doctolib-autoobooking . && docker run --rm -it --init -e DOCTOLIB_SESSION=YOUR_DOCTOLIB_SESSION -e DOCTOLIB_PROFILES=YOUR_PROFILES doctolib-autoobooking
```

### Configuration

Vous devez configurer deux variables d'environnement :
* `DOCTOLIB_SESSION`
* `DOCTOLIB_PROFILES`

Remplacez `YOUR_DOCTOLIB_SESSION` par la valeur de votre cookie `_doctolib_session` après vous être authentifié sur le site Doctolib.

Remplacez `YOUR_PROFILES` par la liste des lieux parmi lesquels vous souhaitez prendre un rendez-vous pour la vaccination. Vous devez passer les `slug` et les séparer par des virgules.    

Exemple : `DOCTOLIB_PROFILES=centre-de-vaccination-covid-19-stade-de-france,vaccinodrome-covid-19-porte-de-versailles`

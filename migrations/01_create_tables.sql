CREATE TABLE utilisateur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'apprenant',
    remember_token VARCHAR(255) NULL,
    photo VARCHAR(255) NULL,
    biographie TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    prix DECIMAL(10,2) NOT NULL DEFAULT 0,
    niveau VARCHAR(20) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    formateur_id INT NOT NULL,
    image_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_cours_user FOREIGN KEY (formateur_id) REFERENCES utilisateur(id)
);

CREATE TABLE atelier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    prix DECIMAL(10,2) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    formateur_id INT NOT NULL,
    nb_places INT NOT NULL DEFAULT 10,
    nb_inscrits INT NOT NULL DEFAULT 0,
    scheduled_at DATETIME NOT NULL,
    duree_minutes INT NOT NULL,
    lieu VARCHAR(255) NOT NULL,
    image_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_atelier_user FOREIGN KEY (formateur_id) REFERENCES utilisateur(id)
);

CREATE TABLE inscription (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    contenu_id INT NOT NULL,
    type ENUM('cours','atelier') NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_inscription_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id)
);

CREATE TABLE avis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    contenu_id INT NOT NULL,
    type ENUM('cours','atelier') NOT NULL,
    note TINYINT NOT NULL,
    commentaire TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_avis_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id)
);

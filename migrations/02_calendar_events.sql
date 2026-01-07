CREATE TABLE calendrier_evenement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NULL,
    titre VARCHAR(150) NOT NULL,
    description TEXT NULL,
    lieu VARCHAR(255) NULL,
    scheduled_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_cal_event_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

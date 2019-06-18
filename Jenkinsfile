pipeline {
  agent {
    kubernetes {
      label 'mautic-translations'
      containerTemplate {
        name 'composer'
        image 'composer:latest'
        ttyEnabled true
        command 'cat'
      }
    }
  }
  stages {
    stage('Dependencies') {
      steps {
        container('composer') {
          ansiColor('xterm') {
            sh """
              composer install --ansi
            """
          }
        }
      }
    }
    stage('Packages build') {
      steps {
        container('composer') {
          ansiColor('xterm') {
            withCredentials([file(credentialsId: 'language-packer-credentials', variable: 'creds')]) {
              sh '''
                cp -v "$creds" etc/config.json
                bin/execute
              '''
            }
          }
        }
      }
    }
  }
}

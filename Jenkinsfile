pipeline {
  triggers {
    cron('H H * * *')
  }
  agent {
    kubernetes {
      label 'mautic-translations'
      inheritFrom 'base'
      containerTemplate {
        name 'composer'
        image 'us.gcr.io/mautic-ma/composer:master'
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
              mkdir PACKS && chmod 777 PACKS
              whoami
            """
          }
          dir('PACKS') {
            checkout changelog: false, poll: false, scm: [$class: 'GitSCM', branches: [[name: 'master']], userRemoteConfigs: [[credentialsId: '1a066462-6d24-4247-bef6-1da084c8f484', url: 'git@github.com:mautic-inc/mautic-language-packs.git']]]
          }
        }
      }
    }
    stage('Packages build') {
      steps {
        container('composer') {
          withCredentials([file(credentialsId: 'language-packer-credentials', variable: 'creds')]) {
            sshagent (credentials: ['1a066462-6d24-4247-bef6-1da084c8f484']) {
              sh '''
                set -e
                cp -v "$creds" etc/config.json
                bin/execute
                cp -v packages/*/* PACKS
                cd PACKS
                cat *.json | jq -s '{"languages":.}' > manifest.json

                if [ -z "$(git status --porcelain)" ]; then
                  # Working directory clean (no changes)
                  exit 0
                fi

                git add .
                git commit -m 'automatic language build'
                git push --set-upstream origin HEAD:master
              '''
            }
          }
        }
      }
    }
  }
  post {
    failure {
      script {
        echo slackSend (color: '#FF0000', message: "Language packer job failed.")
      }
    }
  }
}

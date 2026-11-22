"""Copy project files into an isolated test project."""

from pathlib import Path
import shutil


def copy_application(project, target):
    source = project / 'app'

    def ignore_installed_modules(directory, entries):
        directory = Path(directory)
        if directory != source:
            return []
        return [entry for entry in entries if (directory / entry / '.fusionphp-plugin').is_file()]

    shutil.copytree(source, target / 'app', ignore=ignore_installed_modules)
